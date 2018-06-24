#!/usr/bin/php
<?php

if (file_exists(__DIR__.'/vendor/autoload.php')) {
    require __DIR__.'/vendor/autoload.php';
} elseif (file_exists(realpath(__DIR__.'/../../autoload.php'))) {
    require realpath(__DIR__.'/../../autoload.php');
} elseif (file_exists(__DIR__.'/../../vendor/autoload.php')) {
    require __DIR__.'/../../vendor/autoload.php';
}

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Console\Application;

/**
 * Class CodeQualityTool
 */
class CodeQualityTool extends Application
{
    /**
     * @var OutputInterface
     */
    private $output;

    /**
     * @var InputInterface
     */
    private $input;

    /**
     * @var bool|string
     */
    private $rootPath;

    /**
     * @var bool|string
     */
    private $binPath;

    const PHP_FILES_IN_SRC = '/^src\/(.*)(\.php)$/';

    /**
     * CodeQualityTool constructor.
     */
    public function __construct()
    {
        parent::__construct('Code Quality Tool', '1.0.0');

        $this->rootPath = realpath(__DIR__.'/../../../');
        $this->binPath = realpath($this->rootPath.'/vendor/bin');
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     *
     * @return int|void
     * @throws Exception
     */
    public function doRun(InputInterface $input, OutputInterface $output)
    {
        $this->input = $input;
        $this->output = $output;

        $output->writeln('<fg=white;options=bold;bg=red>Dovab Code Quality Tool</fg=white;options=bold;bg=red>');
        $output->writeln('<info>Fetching files</info>');
        $files = $this->extractCommitedFiles();

        $output->writeln('<info>Check composer</info>');
        $this->checkComposer($files);

        $output->writeln('<info>Running PHPLint</info>');
        if (!$this->phpLint($files)) {
            throw new Exception('There are some PHP syntax errors!');
        }

        $output->writeln('<info>Fixing code style</info>');
        if (!$this->autoFixCodeStyle($files)) {
            throw new Exception(sprintf('Could not auto fix everything!'));
        }

        $output->writeln('<info>Checking code style with PHPCS</info>');
        if (!$this->checkCodeStyle($files)) {
            throw new Exception(sprintf('There are coding standard violations which could not be fixed automatically!'));
        }

        /*$output->writeln('<info>Checking code mess with PHPMD</info>');
        if (!$this->phPmd($files)) {
            throw new Exception(sprintf('There are PHPMD violations!'));
        }*/

        $output->writeln('<info>Running unit tests</info>');
        if (!$this->unitTests()) {
            throw new Exception('Fix the unit tests!');
        }

        $output->writeln('<info>Everything checks out!</info>');
    }

    /**
     * @param array $files
     *
     * @throws Exception
     */
    private function checkComposer(array $files)
    {
        $composerJsonDetected = false;
        $composerLockDetected = false;

        foreach ($files as $file) {
            if ('composer.json' === $file) {
                $composerJsonDetected = true;
            }

            if ('composer.lock' === $file) {
                $composerLockDetected = true;
            }
        }

        if ($composerJsonDetected && !$composerLockDetected) {
            throw new Exception('composer.lock must be commited if composer.json is modified!');
        }
    }

    /**
     * @return array
     */
    private function extractCommitedFiles()
    {
        $output = array();
        $rc = 0;

        exec('git rev-parse --verify HEAD 2> /dev/null', $output, $rc);

        $against = '4b825dc642cb6eb9a060e54bf8d69288fbee4904';
        if (0 === $rc) {
            $against = 'HEAD';
        }

        exec("git diff-index --cached --name-status $against | egrep '^(A|M)' | awk '{print $2;}'", $output);

        return $output;
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    private function phpLint(array $files)
    {
        $needle = '/(\.php)$/';
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $process = new Process(sprintf('php -l %s', $file));
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));

                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    private function phPmd(array $files)
    {
        $needle = self::PHP_FILES_IN_SRC;
        $succeed = true;

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $process = new Process(sprintf('%s/phpmd %s text controversial', $this->binPath, $file), $this->rootPath);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->output->writeln($file);
                $this->output->writeln(sprintf('<error>%s</error>', trim($process->getErrorOutput())));
                $this->output->writeln(sprintf('<info>%s</info>', trim($process->getOutput())));
                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }

    /**
     * @return mixed
     */
    private function unitTests()
    {
        $phpunit = new Process(sprintf('%s/simple-phpunit', $this->binPath), $this->rootPath);
        $phpunit->setTimeout(3600);

        $phpunit->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $phpunit->isSuccessful();
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    private function autoFixCodeStyle(array $files)
    {
        $succeed = true;

        foreach ($files as $file) {
            $srcFile = preg_match(self::PHP_FILES_IN_SRC, $file);
            if (!$srcFile) {
                continue;
            }

            $phpCsFixer = new Process(sprintf('%s/php-cs-fixer fix %s --fixers=@Symfony', $this->binPath, $file), $this->rootPath);
            $phpCsFixer->run();

            if (!$phpCsFixer->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));

                if ($succeed) {
                    $succeed = true;
                }
            }
        }

        return $succeed;
    }

    /**
     * @param array $files
     *
     * @return bool
     */
    private function checkCodeStyle(array $files)
    {
        $succeed = true;
        $needle = self::PHP_FILES_IN_SRC;

        foreach ($files as $file) {
            if (!preg_match($needle, $file)) {
                continue;
            }

            $phpCsFixer = new Process(sprintf('%s/phpcs --standard=Dovab %s', $this->binPath, $file), $this->rootPath);
            $phpCsFixer->enableOutput();
            $phpCsFixer->run();

            if (!$phpCsFixer->isSuccessful()) {
                $this->output->writeln(sprintf('<error>%s</error>', trim($phpCsFixer->getOutput())));

                if ($succeed) {
                    $succeed = false;
                }
            }
        }

        return $succeed;
    }
}

$console = new CodeQualityTool();
$console->run();
