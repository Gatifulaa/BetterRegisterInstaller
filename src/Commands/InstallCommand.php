<?php

namespace Gatifulaa\BetterRegister\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\{info, error, comment};

class InstallCommand extends Command
{
    protected $description = 'Instala o módulo BetterRegister';
    protected $signature = 'register:install {--force}';

    public function handle()
    {
        $this->info('Iniciando instalação automática do BetterRegister...');

        // URL da sua release específica (BetterRegisterModule.tar.gz)
        $moduleDownloadUrl = 'https://github.com/Gatifulaa/PterodactylBetterRegister/releases/download/1.1.0/BetterRegisterModule.tar.gz';
        $panelRoot = base_path();

        $this->comment('Baixando e extraindo o módulo principal...');

        try {
            // Baixa o arquivo tar.gz e extrai diretamente para a raiz do painel
            // Este comando assume que o tar.gz não tem uma pasta raiz extra, ou que você lida com isso na estrutura do arquivo .tar.gz
            $command = "curl -s -L {$moduleDownloadUrl} | tar -xzf - -C {$panelRoot}";
            $process = Process::fromShellCommandline($command);
            $process->setTimeout(120);
            $process->run();

            if (!$process->isSuccessful()) {
                throw new \RuntimeException($process->getErrorOutput());
            }

        } catch (\Exception $e) {
            $this->error('Falha ao baixar ou extrair o módulo. Tente rodar como (sudo su) ou peça ajuda no discord (em breve).');
            $this->error($e->getMessage());
            return;
        }

        $this->comment('Limpando cache do painel...');
        $this->call('view:clear');
        $this->call('cache:clear');

        $this->info('Instalando dependencias locais do Pterodactyl (yarn install)...');
        $this->runProcess("cd {$panelRoot} && yarn install", 300);
        
        $this->info('Compilando assets frontend (yarn build:production)...');
        $this->runProcess("cd {$panelRoot} && export NODE_OPTIONS=--openssl-legacy-provider && yarn build:production", 300);

        $this->info('Módulo BetterRegister instalado com sucesso!');
        return;
    }

    /**
     * Helper para rodar processos de shell e exibir a saída.
     */
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