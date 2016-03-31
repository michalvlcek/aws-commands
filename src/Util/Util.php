<?php

namespace Util;

use Aws\Iam\IamClient;

class Util {

	const ASSUME_ROLE_ACTION = 'sts:AssumeRole';
	const RESOURCE_REGEX = '#arn:aws:iam::([^:]*):role/(\w+)$#i'; // arn:aws:iam::012345678901:role/roleName

	public static function assumedRoles() {
		$c = new IamClient([
			'version' => 'latest',
			'region' => 'us-east-1', // ?
		]);

		$username = $c->getUser()->search('User.UserName');
		$groups = $c->listGroupsForUser(['UserName' => $username])->search('Groups[].GroupName');
		$policies = [];
		foreach ($groups as $group) {
			$managedPolicies = $c->ListAttachedGroupPolicies(['GroupName' => $group])->search('AttachedPolicies[].PolicyArn');
			$policies = array_merge($policies, $managedPolicies);
		}
		$roles = [];

		foreach ($policies as $policy) {
			$version = $c->getPolicy(['PolicyArn' => $policy])->search('Policy.DefaultVersionId');
			$policyDoc = urldecode($c->getPolicyVersion([
				'PolicyArn' => $policy,
				'VersionId' => $version,
			])->search('PolicyVersion.Document'));
			$roles = array_merge($roles, self::getAssumedRoles($policyDoc));
		}

		return $roles;
	}

	/**
	 * Returns assumed roles.
	 * It means if on PolicyDocument is statement which allows action "assumeRole" for some sub-account resource.
	 * @param string $policyDoc
	 * @return array
	 */
	protected static function getAssumedRoles($policyDoc) {
		$roles = [];
		$json = json_decode($policyDoc, true);
		foreach ($json['Statement'] as $statement) {
			if ($statement['Effect'] === 'Allow') {
				if (in_array(self::ASSUME_ROLE_ACTION, $statement['Action'])) {
					if (preg_match(self::RESOURCE_REGEX, $statement['Resource'], $matches)) {
						$roles[] = ['account' => $matches[1], 'role' => $matches[2]];
					}
				}
			}
		}
		return $roles;
	}
}
