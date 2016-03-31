Useful (symfony) commands for working with AWS.

### # installation

* `composer install`
* create `.env` file from example (`cp .env.example .env`) and setup credentials:
  * if you already have AWS credentials file `~/.aws/credentials` you can copy *access keys* from there
  * if you don't you can create new *access keys* at http://aws.amazon.com/developers/access-keys/

### # usage
* `php app.php` - for list of all commands
* `php app.php name:of:command [[option] --argument]`

### # commands
name | description
--- | ---
`demo:greet` | Example command.
[`ec2:hosts-info`](#ec2hosts-info) | Creating ""`/etc/hosts`" records from all EC2 instances (name and IP).
[`iam:assumed-roles`](#iamassumed-roles) | Lists assumed roles from group policies.

----

#### `ec2:hosts-info`

Command which allows to grab informations from all EC2 instances (across all regions) in "hosts" style format.
Default call (with no argument) prints output to stdOut. If you set `file` argument output is appended to specified file.

```sh
php app.php ec2:hosts-info # dump to std output
```
```sh
php app.php ec2:hosts-info --file=/etc/hosts # dump to file
```

results in:

```
 12.12.123.123  SomeName        # i-c12ab06c
 12.12.12.1     AnotherName     # i-05fce0883ca1d7f12
```
----
#### `iam:assumed-roles`

Command attempts to extract all possible assumed roles.
It is done by querying user associated groups and their policyes.
Command which allows to grab informations from all EC2 instances (across all regions) in "hosts" style format.
Default call (with no argument) prints output to stdOut. If you set `file` argument output is appended to specified file.

```sh
php app.php iam:assumed-roles
```

results in:

```php
array:2 [
  0 => array:2 [
    "account" => "012345678901"
    "role" => "roleName"
  ],
  1 => array:2 [
    "account" => "112345678901"
    "role" => "anotherRoleName"
  ]
]
```
