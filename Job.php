<?php

namespace FreePBX\modules\Endpointmonitor;

use FreePBX;
use FreePBX\Job\TaskInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

class Job implements TaskInterface {
        public static function run(InputInterface $input, OutputInterface $output) {
                try {
                        return FreePBX::Endpointmonitor()->runBackgroundMonitor($output);
                } catch (Throwable $e) {
                        $output->writeln('<error>EndPoint Monitor background job failed: ' . $e->getMessage() . '</error>');
                        return false;
                }
        }
}
