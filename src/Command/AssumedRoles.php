<?php

namespace Command;

use Aws\Iam\IamClient;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Util\Util;

class AssumedRoles extends Command {

	const ASSUME_ROLE_ACTION = 'sts:AssumeRole';
	const RESOURCE_REGEX = '#arn:aws:iam::([^:]*):role/(\w+)$#i'; // arn:aws:iam::012345678901:role/roleName

	protected function configure() {
		$this
			->setName('iam:assumed-roles')
			->setDescription('Lists assumed roles from group policies');
	}

	protected function execute(InputInterface $input, OutputInterface $output) {
		$roles = Util::assumedRoles();

		if (!$output->isQuiet()) {
			dump($roles);
		}

		return $roles;
	}
}
