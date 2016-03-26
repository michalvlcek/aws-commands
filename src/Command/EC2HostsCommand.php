<?php

namespace Command;

use Aws\Ec2\Ec2Client;
use Aws\Ec2\Exception\Ec2Exception;
use SplFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class EC2HostsCommand extends Command {

	const COMMENT_SECTION_HEADER = '# AWS records - start';
	const COMMENT_SECTION_FOOTER = '# AWS records - end';

	protected function configure() {
		$this
			->setName('ec2:hosts-info')
			->setDescription('Creating `/etc/hosts` records from all EC2 instances (name and IP)')
			->addOption(
				'file',
				null,
				InputOption::VALUE_REQUIRED,
				'File to write hosts records (e.g. /etc/hosts)',
				FALSE
			);
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		try {
			$regions = $this->getRegions();
			$instances = $this->getInstancesData($regions);
			if ($input->getOption('file') === FALSE) {
				$this->dumpTable($output, $instances);
			} else {
				$this->dumpToFile($input->getOption('file'), $instances);
			}
		} catch (EC2Exception $e) {
			dump($e);
		}
	}

	/**
	 * Write records to stdOut (table formatted).
	 * @param OutputInterface $output
	 * @param array $data
	 */
	protected function dumpTable(OutputInterface $output, $data = []) {
		$table = new Table($output);
		$table->setStyle('compact');
		foreach ($data as $instance) {
			if ($instance['ip']) {
				$table->addRow([$instance['ip'], $instance['name'], ' # ' . $instance['id']]);
			}
		}
		$table->render();
	}

	/**
	 * Write records to file.
	 * @param string $filename
	 * @param array $data
	 */
	protected function dumpToFile($filename = '/etc/hosts', $data = []) {
		$tmpName = tempnam(sys_get_temp_dir(), '');
		chmod($tmpName, 0644);

		$file = new SplFileObject($filename);
		$tmp = new SplFileObject($tmpName, 'w');

		// normalize file - extract & remove old AWS records
		$contents = $file->fread($file->getSize());
		if (($startPos = strpos($contents, self::COMMENT_SECTION_HEADER)) !== FALSE) {
			$endPos = strpos($contents, self::COMMENT_SECTION_FOOTER) + strlen(self::COMMENT_SECTION_FOOTER);
			$contents = substr($contents, 0, $startPos) . substr($contents, $endPos);
		}
		$tmp->fwrite(rtrim($contents));
		$tmp->fwrite("\n\n");

		// write new records
		$tmp->fwrite(self::COMMENT_SECTION_HEADER . "\n");
		foreach ($data as $instance) {
			if ($instance['ip']) {
				$tmp->fwrite($instance['ip'] . "   " . $instance['name'] . "   # " . $instance['id'] . "\n");
			}
		}
		$tmp->fwrite(self::COMMENT_SECTION_FOOTER . "\n");

		rename($tmpName, $filename);
	}

	/**
	 * Fetch instance data.
	 * @param array $regions
	 * @return array
	 */
	protected function getInstancesData($regions = []) {
		$result = [];
		foreach ($regions as $region) {
			$ec2 = new Ec2Client([
				'version' => 'latest',
				'region' => $region,
			]);
			$instances = $ec2->describeInstances();
			$path = "Reservations[].Instances[].{name: Tags[?Key == 'Name'].Value | [0], id: InstanceId, state: State.Name, ip: PublicIpAddress}";
			$result = array_merge($result, $instances->search($path));
		}

		return $result;
	}

	/**
	 * Get all available regions.
	 * @return mixed|null
	 */
	protected function getRegions() {
		$ec2client = new Ec2Client([
			'version' => 'latest',
			'region' => getenv('AWS_DEFAULT_REGION'),
		]);
		return $ec2client->describeRegions()->search('Regions[].RegionName');
	}
}
