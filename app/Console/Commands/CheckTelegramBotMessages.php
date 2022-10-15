<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use JsonException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class CheckTelegramBotMessages extends Command
{
    /**
     * $key - word in lowercase
     * $value = script in ./BashCommand folder
     * @var array|string[]
     */
    private array $arrayWords = [
        'привет' => 'hi.bash',
        'hi'     => 'hi.bash',
        'hello'  => 'hi.bash',
        'привіт' => 'hi.bash',
        'xxx'    => 'xxx.bash', //eng
        'ххх'    => 'xxx.bash', //ukr
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'telegram:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check message in TelegramBot';

    /**
     * Execute the console command.
     *
     * @return int
     * @throws JsonException
     */
    public function handle(): int
    {
        $offset = Cache::get('offset');
        $botToken = env('TELEGRAM_BOT_TOKEN');

        /** Checking Bot Token */
        if (!$botToken) {
            $this->error('Added your Telegram Bot Token to .env');
            return Command::FAILURE;
        }

        /** Get messages from Telegram Bot $res - stream $result - associative array*/
        $res = Http::get('https://api.telegram.org/bot'.$botToken.'/getUpdates',[
            'offset' => $offset ?: -1,
            'allowed_updates' => 'message',
        ]);
        $result = json_decode($res->body(), false, 512, JSON_THROW_ON_ERROR);

        /** Checking problem in response */
        if (!$result->ok) {
            $this->error('Telegram API Error: '.$result->error_code.' '.$result->description);
            return Command::FAILURE;
        }

        /** Create collection of messages */
        $updatesCollection = collect($result->result);

        /** Update offset */
        if ($last = $updatesCollection->last()) {
            Cache::put('offset', $last->update_id + 1);
        } else {
            $this->info('Nothing to update!');
            return Command::SUCCESS;
        }

        /** Checking words from an array in the text message */
        //TODO:Move to a separate function or class
        foreach($updatesCollection as $item) {
            if (array_key_exists(mb_strtolower($item->message->text), $this->arrayWords)) {
                $script = $this->arrayWords[mb_strtolower($item->message->text)];
                $this->info('Start script: '.$script);
                $process = new Process(['bash','./BashCommand/'.$script]);
                $process->run();
                if (!$process->isSuccessful()) {
                    throw new ProcessFailedException($process);
                }

                echo $process->getOutput();
            }
        }
        return Command::SUCCESS;
    }
}
