<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class UsersProcessCommand extends Command {
    protected $signature = 'users:process';
    protected $description = 'Processa users.json em stream manual e armazena em cache por chunk';

    public function handle(): int {
        $start = microtime(true);
        $this->info('Comando sendo executado, aguarde...');

        $file = storage_path('app/users.json');
        if (!file_exists($file)) {
            $this->error('Arquivo users.json não encontrado.');
            return self::FAILURE;
        }

        $handle = fopen($file, 'r');
        if (!$handle) {
            $this->error('Falha ao abrir o arquivo.');
            return self::FAILURE;
        }

        $chunkIndex = 0;
        while (Cache::has("users_chunk_{$chunkIndex}")) {
            Cache::forget("users_chunk_{$chunkIndex}");
            $chunkIndex++;
        }
        Cache::forget('users_total_chunks');
        Cache::forget('users_status');

        $buffer = '';
        $depth = 0;
        $inObject = false;
        $chunk = [];
        $chunkIndex = 0;

        $this->line('');
        $this->line('Iniciando leitura e processamento do arquivo...');
        $bar = $this->output->createProgressBar(100000);
        $bar->start();

        while (($char = fgetc($handle)) !== false) {
            if ($char === '{') {
                $inObject = true;
                $depth = 1;
                $buffer = '{';
                break;
            }
        }

        while (!feof($handle)) {
            $char = fgetc($handle);
            if ($char === false) break;

            if ($inObject) {
                $buffer .= $char;
                if ($char === '{') $depth++;
                elseif ($char === '}') $depth--;

                if ($depth === 0) {
                    $user = json_decode($buffer, true);
                    if (json_last_error() === JSON_ERROR_NONE && isset($user['id'])) {
                        $chunk[] = $user;
                        if (count($chunk) >= 1000) {
                            Cache::put("users_chunk_{$chunkIndex}", $chunk, now()->addDay());
                            $chunk = [];
                            $chunkIndex++;
                        }
                    }
                    $inObject = false;
                    $buffer = '';

                    while (($char = fgetc($handle)) !== false && $char !== '{') {
                        if ($char === ']') break 2;
                    }

                    if ($char === '{') {
                        $inObject = true;
                        $depth = 1;
                        $buffer = '{';
                    }

                    $bar->advance();
                }
            }
        }

        fclose($handle);

        if (!empty($chunk)) {
            Cache::put("users_chunk_{$chunkIndex}", $chunk, now()->addDay());
            $chunkIndex++;
        }

        Cache::put('users_total_chunks', $chunkIndex, now()->addDay());
        Cache::put('users_status', 'done', now()->addDay());

        $bar->finish();
        $this->newLine(2);

        $duration = round(microtime(true) - $start, 2);
        $this->info("Processamento concluído em {$duration}s. Total de chunks: {$chunkIndex}");

        return self::SUCCESS;
    }
}
