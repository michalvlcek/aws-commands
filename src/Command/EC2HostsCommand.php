<?php

namespace Command;

use Aws\Credentials\Credentials;
use Aws\Ec2\Ec2Client;
use Aws\Sts\StsClient;
use SplFileObject;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Util\Util;

class EC2HostsCommand extends Command {

	const COMMENT_SECTION_HEADER = '# AWS records - start';
	const COMMENT_SECTION_FOOTER = '# AWS records - end';

	const ROLE_ARN = 'arn:aws:iam::%s:role/%s';

	/** @var ProgressBar */
	private $progress;

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
		$this->progress = new ProgressBar($output);
		$this->progress->setFormat('%message%');
		$this->progress->start();

		$this->progress->setMessage("Fetching assumed roles...");
		$roles = Util::assumedRoles();

		$regions = $this->getRegions();
		$instances = $this->getInstancesData($regions);

		foreach ($roles as $role) {
			$c = $this->getCredentials($role);
			$credentials = new Credentials(
				$c['AccessKeyId'],
				$c['SecretAccessKey'],
				$c['SessionToken'],
				$c['Expiration']
			);
			$instances = array_merge($instances, $this->getInstancesData($regions, $credentials));
		}



		$this->progress->finish();
		$this->progress->setMessage("Fetching regions...");
		$output->writeln("\n");
		if ($input->getOption('file') === FALSE) {
			$this->dumpTable($output, $instances);
		} else {
			$this->dumpToFile($input->getOption('file'), $instances);
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
	 * @param Credentials|null $credentials
	 * @return array
	 */
	protected function getInstancesData($regions = [], Credentials $credentials = null) {
		$result = [];
		foreach ($regions as $region) {
			$this->progress->setMessage("Fetching instances from $region...");
			$this->progress->advance();
			$ec2 = new Ec2Client([
				'version' => 'latest',
				'region' => $region,
				'credentials' => $credentials,
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
		$this->progress->setMessage("Fetching regions...");
		$this->progress->advance();
		$ec2client = new Ec2Client([
			'version' => 'latest',
			'region' => getenv('AWS_DEFAULT_REGION'),
		]);
		return $ec2client->describeRegions()->search('Regions[].RegionName');
	}

	/**
	 * @param array $role
	 * @return array
	 */
	protected function getCredentials($role = []) {
		$c = new StsClient([
			'version' => 'latest',
			'region' => 'us-east-1',
		]);

		$credentials = $c->assumeRole([
			'RoleArn' => sprintf(self::ROLE_ARN, $role['account'], $role['role']),
			'RoleSessionName' => 'aws-commands',
		])->search('Credentials');
		return $credentials;
	}
}
