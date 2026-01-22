<?php

namespace Gatifulaa\BetterRegister\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;
use function Laravel\Prompts\{progress, text, select, confirm, info, error, warning, spin};

class InstallCommand extends Command
{
    protected $description = 'Instala o módulo BetterRegister';
    protected $signature = 'register:install {--force}';
    protected $client;

    public function __construct()
    {
        parent::__construct();
        $this->client = new Client();
    }

    public function handle()
    {
        $panelRoot = base_path();
        // --- URL da sua release específica ---
        $moduleDownloadUrl = 'https://github.com/Gatifulaa/PterodactylBetterRegister/releases/download/1.1.0/BetterRegisterModule.tar.gz';
        $fileToModify = 'resources/scripts/components/auth/LoginContainer.tsx';

        // --- Detecção de Usuário Padrão ---
        $userDetails = posix_getpwuid(fileowner('public'));
        $defaultUser = $userDetails['name'] ?? 'www-data';
        $groupDetails = posix_getgrgid(filegroup('public'));
        $defaultGroup = $groupDetails['name'] ?? 'www-data';
        
        $user = $defaultUser;
        $group = $defaultGroup;

        // --- Verificação e Instalação de Arquivos (Baseado em SHA1) ---
        if ($this->isModuleInstalled($fileToModify)) {
            info('O módulo de registro já parece estar instalado.');
            return;
        }

        // --- Menu Interativo de Usuário e Grupo (omito para brevidade, use o código anterior) ---
        // if (!$this->option('force')) { ... }
        
        // --- Processo de Instalação com Progresso ---
        $progress = progress(label: 'Instalando o Módulo BetterRegister', steps: 4);
        $progress->start();

        // Passo 1: Download e Extração
        spin(
            // Use --strip-components=1 se o seu tar.gz tiver uma pasta raiz extra
            fn() => $this->runProcess("curl -s -L {$moduleDownloadUrl} | tar -xzf - -C {$panelRoot}"),
            'Baixando e extraindo o módulo principal...'
        );
        $progress->advance();

        // Passo 2: Modificando o TSX (lógica de exemplo do VertisanPRO)
        $installTsx = $this->installContainer($fileToModify);
        spin(
            fn() => $installTsx,
            'Configurando a página de login...'
        );

        if (!$installTsx) {
            $progress->finish();
            error('Não foi possível instalar o módulo. Você pode estar usando um tema incompatível.');
            return;
        }
        $progress->advance();
        
        // Passo 3: Limpando Cache
        spin(
            fn() => exec('php artisan view:clear && php artisan config:clear'),
            'Limpando cache...'
        );
        $progress->advance();

        // Passo 4: Definindo Permissões e Build do Frontend
        // Use o $user e $group selecionados no menu (omitido acima)
        spin(
            fn() => $this->runProcess("chown -R {$user}:{$group} {$panelRoot}/*"),
            "Definindo permissões corretas..."
        );
        
        info('Compilando assets frontend (yarn build:production)...');
        $this->runProcess("cd {$panelRoot} && yarn install && export NODE_OPTIONS=--openssl-legacy-provider && yarn build:production", 600); // Timeout maior para o build

        $progress->advance();
        $progress->finish();

        info('Módulo BetterRegister instalado com sucesso! 🎉');
        return;
    }

    /**
     * Helper para rodar processos de shell e exibir a saída.
     */
    protected function runProcess($command, $timeout = 60)
    {
        $process = Process::fromShellCommandline($command);
        $process->setTimeout($timeout);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new \RuntimeException($process->getErrorOutput());
        }
    }

    /**
     * Helper para verificar se o módulo já está instalado.
     */
    private function isModuleInstalled(string $file): bool
    {
        // Esta URL é para verificar o arquivo original do Pterodactyl, não seu repo.
        $res = $this->client->get("https://raw.githubusercontent.com{$file}");
        return sha1($res->getBody()) !== sha1(file_get_contents(base_path($file)));
    }

    /**
     * Helper para modificar o arquivo LoginContainer.tsx.
     */
    private function installContainer(string $file): bool
    {
        $currentFileContents = file_get_contents(base_path($file));
        
        $insert = '
                    <div css={tw`mt-6 text-center`}>
                        <Link
                            to={\'/auth/register\'}
                            css={tw`text-xs text-neutral-500 tracking-wide no-underline uppercase`}
                        >
                            Criar uma conta
                        </Link>
                    </div>
            ';
        $pos = strpos($currentFileContents, '                </LoginFormContainer>');

        if (!$pos)
            return false;

        file_put_contents(base_path($file), substr_replace($currentFileContents, $insert, $pos, 0));

        return true;
    }
}
