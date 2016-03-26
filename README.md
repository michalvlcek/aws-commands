Useful (symfony) commands for working with AWS.

### # installation

* `composer install`
* create `.env` file from example (`cp .env.example .env`) and setup

### # usage
* `php app.php` - for list of all commands
* `php app.php name:of:command [[option] --argument]`

### # commands

* `demo:greet Fabien` - example command
* `ec2:hosts-info` - creating `/etc/hosts` records from all EC2 instances (name and IP)
