<?php

namespace Gatifulaa\BetterRegister\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\{info, error, comment, select, confirm};

class InstallCommand extends Command
{
    protected $description = 'Instala o módulo BetterRegister';
    protected $signature = 'register:install {--force}';

    public function handle()
    {
        $this->info('Iniciando instalação automática do BetterRegister...');

        $moduleDownloadUrl = 'https://github.com';
        $panelRoot = base_path();
        
        // --- NOVO BLOCO: Escolha do Usuário do Servidor Web ---
        $user = 'www-data'; // Valor padrão
        $group = 'www-data'; // Valor padrão

        if (!$this->option('force')) {
            $user = select(
                label: 'Selecione o usuário do seu servidor web (comum: www-data, nginx, ou apache):',
                options: [
                    'www-data' => 'www-data',
                    'nginx' => 'nginx',
                    'apache' => 'apache',
                ],
                default: 'www-data'
            );

            $confirmGroup = confirm(
                label: "O grupo é o mesmo que o usuário ({$user})?",
                default: true,
            );

            if (!$confirmGroup) {
                $group = select(
                    label: 'Selecione o grupo do seu servidor web:',
                    options: [
                        'www-data' => 'www-data',
                        'nginx' => 'nginx',
                        'apache' => 'apache',
                    ],
                    default: 'www-data'
                );
            } else {
                $group = $user;
            }
        }
        // --- FIM NOVO BLOCO ---


        $this->comment('Baixando e extraindo o módulo principal...');
        try {
            $command = "curl -s -L {$moduleDownloadUrl} | tar -xzf - -C {$panelRoot}";
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(120);
            $process->run();
            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }
        } catch (\Exception $e) {
            $this->error('Falha ao baixar ou extrair o módulo.');
            return;
        }

        $this->comment('Limpando cache do painel...');
        $this->call('view:clear');
        $this->call('cache:clear');
        
        $this->info('Configurando permissões de arquivos...');
        // --- NOVO BLOCO: Aplica as permissões usando o usuário/grupo escolhido ---
        try {
            $this->runProcess("chown -R {$user}:{$group} {$panelRoot}/storage {$panelRoot}/bootstrap/cache");
            $this->runProcess("chmod -R 755 {$panelRoot}/storage {$panelRoot}/bootstrap/cache");
        } catch (\RuntimeException $e) {
            $this->warn('Falha ao definir permissões automáticas. Você precisará fazê-lo manualmente.');
        }
        // --- FIM NOVO BLOCO ---


        $this->info('Compilando assets frontend (yarn build:production)...');
        $this->runProcess("cd {$panelRoot} && yarn install && export NODE_OPTIONS=--openssl-legacy-provider && yarn build:production", 300);

        $this->info('Módulo BetterRegister instalado com sucesso! 🎉');
        return;
    }

    protected function runProcess($command, $timeout = 60)
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);
        $process->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }
}

