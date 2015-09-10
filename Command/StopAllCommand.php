<?php

namespace EXSyst\Bundle\WorkerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use EXSyst\Component\Worker\SharedWorker;

class StopAllCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('exsyst:worker:stop-all')
            ->setDescription('Stop this application\'s named shared workers (if suitably configured)')
            ->addOption('also-disable', 'd', InputOption::VALUE_NONE, 'Also disable the workers (if suitably configured)')
            ->addOption('include-remote', 'r', InputOption::VALUE_NONE, 'Stop remote workers in addition to local ones');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $alsoDisable = $input->getOption('also-disable');
        $includeRemote = $input->getOption('include-remote');

        $registry = $this->getContainer()->get('exsyst_worker');

        foreach ($registry->getSharedWorkerNames() as $name) {
            $address = $registry->getSharedWorkerSocketAddress($name);
            $local = SharedWorker::isLocalAddress($address);

            if (!$local && !$includeRemote) {
                $output->writeln('Skipped remote worker <comment>'.$name.'</comment>.');
                continue;
            }

            $factory = $registry->getSharedWorkerFactory($name);
            $profile = $factory->getBootstrapProfile();

            if ($local && $alsoDisable) {
                if ($profile->getKillSwitchPath() !== null) {
                    $registry->disableSharedWorker($name);
                    $output->writeln('Disabled local worker <comment>'.$name.'</comment>.');
                } else {
                    $output->writeln('Couldn\'t disable local worker <comment>'.$name.'</comment> (please configure a kill switch for this feature to work).');
                }
            }

            if ($profile->getStopCookie() !== null) {
                if ($registry->stopSharedWorker($name)) {
                    $output->writeln('Stopped '.($local ? 'local' : 'remote').' worker <comment>'.$name.'</comment>.');
                } else {
                    $output->writeln(($local ? 'Local' : 'Remote').' worker <comment>'.$name.'</comment> was not running.');
                }
            }
        }
    }
}
