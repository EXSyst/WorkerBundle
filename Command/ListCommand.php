<?php

namespace EXSyst\Bundle\WorkerBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use EXSyst\Component\Worker\Exception\ConnectException;
use EXSyst\Component\Worker\Internal\IdentificationHelper;
use EXSyst\Component\Worker\Status\WorkerStatus;

class ListCommand extends ContainerAwareCommand
{
    private $registry;

    private function getRegistry()
    {
        if (!isset($this->registry)) {
            $this->registry = $this->getContainer()->get('exsyst_worker');
        }

        return $this->registry;
    }

    protected function configure()
    {
        $this
            ->setName('exsyst-worker:list')
            ->setDescription('List this application\'s named shared workers')
            ->addOption('long', 'l', InputOption::VALUE_NONE, 'Long format : show details about each worker (implies -c)')
            ->addOption('color-names', 'c', InputOption::VALUE_NONE, 'Color names depending on status (green = running, red = disabled ; implied by -l)')
            ->addOption('no-status', 'x', InputOption::VALUE_NONE, 'Do not try to connect to any worker to query status data, to save time (only meaningful with -l or -c ; -r will be ignored)')
            ->addOption('remote-status', 'r', InputOption::VALUE_NONE, 'Also try to connect to remote workers to query status data (may take a while ; only meaningful with -l or -c, ignored if -x is specified)')
            ->addOption('factory', 'f', InputOption::VALUE_REQUIRED, 'Only list workers managed by the given factory');
    }

    private function parseCommandLine(InputInterface $input, &$long, &$colorNames, &$noStatus, &$remoteStatus, &$factory)
    {
        $long = $input->getOption('long');
        $colorNames = $long || $input->getOption('color-names');
        $noStatus = !$colorNames || $input->getOption('no-status');
        $remoteStatus = !$noStatus && $input->getOption('remote-status');
        $factory = $input->hasOption('factory') ? $input->getOption('factory') : null;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->parseCommandLine($input, $long, $colorNames, $noStatus, $remoteStatus, $factory);

        $registry = $this->getRegistry();

        if ($long) {
            $rows = [];
        }

        foreach ($registry->getSharedWorkerNames() as $name) {
            $wFactory = $registry->getSharedWorkerFactoryName($name);
            if ($factory !== null && $wFactory != $factory) {
                continue;
            }

            if (isset($rows)) {
                $this->getData($name, $noStatus, $remoteStatus, $socketAddress, $pid, $status);
                $rows[] = $this->makeRow($name, $wFactory, $pid, $socketAddress, $status);
            } elseif ($colorNames) {
                $this->getData($name, $noStatus, $remoteStatus, $socketAddress, $pid, $status);
                $flags = $this->getFlags($name, $pid, IdentificationHelper::isLocalAddress($socketAddress), $status);
                $output->writeln($this->makeColoredName($name, $flags));
            } else {
                $output->writeln($name);
            }
        }

        if (isset($rows)) {
            $output->writeln('Flags legend :');
            $output->writeln('<comment>l</comment> = Running (live), <comment>r</comment> = Remote, <comment>a</comment> = Admin cookie present');
            $output->writeln('<comment>d</comment> = Disabled, <comment>k</comment> = Kill switch present, <comment>e</comment> = Eagerly starting');
            $output->writeln('<comment>d</comment> implies <comment>k</comment>');
            $table = new Table($output);
            $table->setHeaders(['Flags', 'Name', 'Factory', 'PID', 'Network address', 'Status']);
            $table->setRows($rows);
            $table->setStyle('borderless');
            $table->render();
        }
    }

    private function getData($name, $noStatus, $remoteStatus, &$socketAddress, &$pid, &$status)
    {
        $registry = $this->getRegistry();
        $socketAddress = $registry->getSharedWorkerSocketAddress($name);
        $pid = $registry->getSharedWorkerProcessId($name);
        if (IdentificationHelper::isLocalAddress($socketAddress) ? !$noStatus : $remoteStatus) {
            try {
                $status = $registry->querySharedWorker($name);
            } catch (ConnectException $e) {
                $status = null;
            }
        } else {
            $status = null;
        }
    }

    private function getFlags($name, $pid, $local, WorkerStatus $status = null)
    {
        $registry = $this->getRegistry();
        $factory = $registry->getSharedWorkerFactory($name);
        $profile = $factory->getBootstrapProfile();
        $flags = ['-', '-', '-', '-', '-'];
        if ($pid !== null || $status !== null) {
            $flags[0] = 'l';
        }
        if (!$local) {
            $flags[1] = 'r';
        } elseif ($registry->isSharedWorkerDisabled($name)) {
            $flags[3] = 'd';
        } elseif ($profile->getKillSwitchPath() !== null) {
            $flags[3] = 'k';
        }
        if ($profile->getAdminCookie() !== null) {
            $flags[2] = 'a';
        }
        if ($registry->isSharedWorkerEagerlyStarting($name)) {
            $flags[4] = 'e';
        }

        return $flags;
    }

    private function makeColoredName($name, array $flags)
    {
        return ($flags[0] == 'l') ? ('<fg=green>'.$name.'</fg=green>') : (($flags[3] == 'd') ? ('<fg=red>'.$name.'</fg=red>') : $name);
    }

    private function makeRow($name, $wFactory, $pid, $socketAddress, WorkerStatus $status = null)
    {
        $flags = $this->getFlags($name, $pid, IdentificationHelper::isLocalAddress($socketAddress), $status);

        return [
            implode($flags),
            $this->makeColoredName($name, $flags),
            $wFactory,
            ($pid !== null) ? strval($pid) : '<fg=blue>unknown</fg=blue>',
            IdentificationHelper::isNetworkExposedAddress($socketAddress) ? IdentificationHelper::stripScheme($socketAddress) : '<fg=blue>local-only</fg=blue>',
            ($status !== null) ? $status->getTextStatus() : '<fg=blue>no data</fg=blue>',
        ];
    }
}
