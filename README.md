# lulo
A minimal ORM for PHP inspired by Django.

# What's this?
This is a small and easy to use ORM based in Django's ORM API for PHP.

# Requirements
PHP 5.4 and dependences installed by composer (AdoDB and Twig template syste).

# Documentation
## Local configuration
Create a configuration.local.php in your web server with the structure defined in configuration.local.example.php.

This file must contain access credential to your database as seen in the example:

```php
function get_db_settings(){
    $db_settings = [
        "server" => "<DB SERVER>",
        "user" => "<DB USER>",
        "password" => "<DB PASSWORD>",
        "database" => "<DATABASE>"
    ];
    return $db_settings;
}
```

This local configuration file will be loaded automatically from **configuration.php** and will be used to access database.

# Examples

There is a test module gives you several examples of using Lulo and its query system.

# License
MIT License.

# Author
Created by Diego J. Romero López at intelligenia
(diegoREMOVETHIS@REMOVETHISintelligenia.com)
