<?php

namespace App\Command;

use App\Command\Service\SilenceService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class SilenceCommand extends Command
{
    protected static $defaultName = 'silence:create-json';
    private SilenceService $service;

    public function __construct(string $name = null, SilenceService $service)
    {
        parent::__construct($name);
        $this->service = $service;
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'xmlPath',
                null,
                InputOption::VALUE_REQUIRED,
                'provide path to source xnk file',
                null
            )
            ->addOption(
                'chapterTimout',
                null,
                InputOption::VALUE_REQUIRED,
                'provide timout to start new chapter',
                2
            )
            ->addOption(
                'partTimout',
                null,
                InputOption::VALUE_REQUIRED,
                'provide timout to start new chapter',
                0.5
            )
            ->addOption(
                'maximumChapterDuration',
                null,
                InputOption::VALUE_REQUIRED,
                'provide timout to start new chapter',
                180
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->service->setSourceXmlFilePath($input->getOption('xmlPath'));
        $this->service->setChapterTimoutParameters(
            $input->getOption('chapterTimout'),
            $input->getOption('partTimout'),
            $input->getOption('maximumChapterDuration')
        );

        $jsonOutput = json_encode(
            [
                'timestamp' => (new \DateTime('now'))->format('H:i:s d.m.Y'),
                'segments' => $this->service->process()
            ],
            JSON_PRETTY_PRINT);

        $this->service->out($jsonOutput);

        $output->writeln('<info>Processing complete</info>');

        return Command::SUCCESS;
    }
}
