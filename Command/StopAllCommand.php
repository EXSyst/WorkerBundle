<?php

/*
 * This file is part of the WorkerBundle package.
 *
 * (c) EXSyst
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EXSyst\Bundle\WorkerBundle\Command;

use EXSyst\Bundle\WorkerBundle\Exception;
use EXSyst\Bundle\WorkerBundle\WorkerRegistry;
use EXSyst\Component\Worker\Bootstrap\WorkerBootstrapProfile;
use EXSyst\Component\Worker\Internal\IdentificationHelper;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class StopAllCommand extends ContainerAwareCommand
{
    /**
     * @var WorkerRegistry|null
     */
    private $registry;

    /**
     * @return WorkerRegistry
     */
    private function getRegistry()
    {
        if (!isset($this->registry)) {
            $this->registry = $this->getContainer()->get('exsyst_worker');
        }

        return $this->registry;
    }

    /** {@inheritdoc} */
    protected function configure()
    {
        $this
            ->setName('exsyst-worker:stop-all')
            ->setDescription('Stop this application\'s named shared workers')
            ->addOption('also-disable', 'd', InputOption::VALUE_NONE, 'Also disable the workers (only local ones, even with -r)')
            ->addOption('include-remote', 'r', InputOption::VALUE_NONE, 'Stop remote workers in addition to local ones (ignored if -k is specified)')
            ->addOption('factory', 'f', InputOption::VALUE_REQUIRED, 'Only stop workers managed by the given factory')
            ->addOption('signal', 'k', InputOption::VALUE_REQUIRED, 'Send the given POSIX signal to the workers instead of the stop message (-r will be ignored)');
    }

    /**
     * @param InputInterface $input
     * @param bool           $alsoDisable
     * @param bool           $includeRemote
     * @param string|null    $factory
     * @param int|null       $signal
     *
     * @throws Exception\InvalidArgumentException
     */
    private function parseCommandLine(InputInterface $input, &$alsoDisable, &$includeRemote, &$factory, &$signal)
    {
        $alsoDisable = $input->getOption('also-disable');
        $includeRemote = $input->getOption('include-remote');
        $factory = $input->hasOption('factory') ? $input->getOption('factory') : null;
        $signal = $input->hasOption('signal') ? $input->getOption('signal') : null;
        if ($signal !== null) {
            $includeRemote = false;
            $signal = self::parseSignal($signal);
        }
    }

    /** {@inheritdoc} */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->parseCommandLine($input, $alsoDisable, $includeRemote, $factory, $signal);

        $registry = $this->getRegistry();

        foreach ($registry->getSharedWorkerNames() as $name) {
            $address = $registry->getSharedWorkerSocketAddress($name);
            $local = IdentificationHelper::isLocalAddress($address);
            if (!$includeRemote & !$local) {
                $output->writeln('Skipped remote worker <comment>'.$name.'</comment>.');
                continue;
            }

            $wFactory = $registry->getSharedWorkerFactoryName($name);
            if ($factory !== null && $wFactory != $factory) {
                $output->writeln('Skipped '.($local ? 'local' : 'remote').' worker <comment>'.$name.'</comment> from factory <comment>'.$wFactory.'</comment>.');
                continue;
            }

            $factory = $registry->getSharedWorkerFactory($name);
            $profile = $factory->getBootstrapProfile();

            if ($alsoDisable & $local) {
                $this->disableWorker($output, $profile, $name, $wFactory);
            }

            $this->stopWorker($output, $profile, $name, $local, $signal, $wFactory);
        }
    }

    /**
     * @param OutputInterface        $output
     * @param WorkerBootstrapProfile $profile
     * @param string                 $name
     * @param string                 $wFactory
     */
    private function disableWorker(OutputInterface $output, WorkerBootstrapProfile $profile, $name, $wFactory)
    {
        if ($profile->getKillSwitchPath() !== null) {
            $this->getRegistry()->disableSharedWorker($name);
            $output->writeln('Disabled local worker <comment>'.$name.'</comment>.');
        } else {
            $output->writeln('Couldn\'t disable local worker <comment>'.$name.'</comment> (please configure a kill switch on factory <comment>'.$wFactory.'</comment> for this feature to work).');
        }
    }

    /**
     * @param OutputInterface        $output
     * @param WorkerBootstrapProfile $profile
     * @param string                 $name
     * @param bool                   $local
     * @param int|null               $signal
     * @param string                 $wFactory
     */
    private function stopWorker(OutputInterface $output, WorkerBootstrapProfile $profile, $name, $local, $signal, $wFactory)
    {
        if ($signal === null) {
            $this->stopWorkerWithMessage($output, $profile, $name, $local, $wFactory);
        } else {
            $this->stopWorkerWithSignal($output, $name, $signal);
        }
    }

    /**
     * @param OutputInterface        $output
     * @param WorkerBootstrapProfile $profile
     * @param string                 $name
     * @param bool                   $local
     * @param string                 $wFactory
     */
    private function stopWorkerWithMessage(OutputInterface $output, WorkerBootstrapProfile $profile, $name, $local, $wFactory)
    {
        if ($profile->getAdminCookie() !== null) {
            if ($this->getRegistry()->stopSharedWorker($name)) {
                $output->writeln('Sent stop message to '.($local ? 'local' : 'remote').' worker <comment>'.$name.'</comment>.');
            } else {
                $output->writeln(($local ? 'Local' : 'Remote').' worker <comment>'.$name.'</comment> was not running.');
            }
        } else {
            $output->writeln('Couldn\'t send stop message to '.($local ? 'local' : 'remote').' worker <comment>'.$name.'</comment> (please configure an admin cookie on factory <comment>'.$wFactory.'</comment> for this feature to work).');
        }
    }

    /**
     * @param OutputInterface $output
     * @param string          $name
     * @param int|null        $signal
     */
    private function stopWorkerWithSignal(OutputInterface $output, $name, $signal)
    {
        $pid = $this->getRegistry()->getSharedWorkerProcessId($name);
        if ($pid !== null) {
            if (posix_kill($pid, $signal)) {
                $output->writeln('Sent signal to local worker <comment>'.$name.'</comment> (PID <comment>'.$pid.'</comment>).');
            } else {
                $output->writeln('Failed sending signal to local worker <comment>'.$name.'</comment> (PID <comment>'.$pid.'</comment>).');
            }
        } else {
            $output->writeln('Couldn\'t send signal to local worker <comment>'.$name.'</comment> because its PID could\'t be identified.');
        }
    }

    /**
     * @param string|int $signal
     *
     * @throws Exception\InvalidArgumentException
     *
     * @return int
     */
    private static function parseSignal($signal)
    {
        if (!function_exists('posix_kill')) {
            throw new Exception\InvalidArgumentException('The -k option requires the PHP POSIX extension');
        }
        if (is_numeric($signal)) {
            return intval($signal);
        } elseif (preg_match('#^[0-9A-Za-z]+$#', $signal) && defined('SIG'.$signal)) {
            return constant('SIG'.$signal);
        } else {
            throw new Exception\InvalidArgumentException('The -k option requires a valid signal number or name');
        }
    }
}
