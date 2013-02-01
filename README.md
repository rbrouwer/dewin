# Dewin

Current Build Status:
[![Build Status](https://travis-ci.org/rbrouwer/dewin.png?branch=master)](https://travis-ci.org/rbrouwer/dewin)

Dewin(DEployment Web INterface or Deinstall Windows or (in Dutch:) De Enige Waardeloze INstaller or ...) is a tool to make deploying of their web-applications easier.

It is a front end for Phing, with some business logic and some other tools to make deployments a lot easier.

A lot of documentation to make it possible to actually use it (to its full potential) will come at a later. Because a large part of the interface will be refactored in a future sprint, the documentation will be written after that.

## System Requirements
* [Zend Framework 1](http://framework.zend.com/downloads/latest#ZF1)
* [PHP SSH2 Library](http://www.php.net/manual/en/book.ssh2.php)
* [Schemasync](http://schemasync.org/) is used for comparing databases. Technically any database difference tool can be used if it outputs SQL
* SQLite (for unit tests)
* [Redbean](http://www.redbeanphp.com/)*
* [Phing >=2.4.13](http://www.phing.info/)*
* [SpikePHPCoverage](http://sourceforge.net/projects/phpcoverage/) (for unit tests)*
* [DirectAdmin API Library](http://forum.directadmin.com/showthread.php?t=258)*

The items marked by the * are installed by the application's installer.

## Installation
* Clone the git repository in the document root of your domain
* Point a webbrowser at it
* Follow the steps of the installer

## So I installed this... Now what?
There are three things you will have to do:

### Create server types 
The first task is to extend the Model_Server_Abstract class in application/models/Server.
The Model_Server_ classes are used in a state-pattern. This allows each servertype to behave differently and to attach different properties to an instance.
The properties attached to an instance will be added to the property file for phing.
Examples are used for unit testing by travis-ci. You can find these examples in application/tests/server.

### Add servers in the database
This tool currently does not have panels to create, read, update and delete many of the entities. To add servers your favorite mysql tool can be used.
The server with id 1 will be used as source server. Currently all applications will be deployed from this server. In a few weeks this will be solved.

### Create and add recipes
Phing is worthless without recipes and this tools is pretty much worthless without phing. For all information about recipes can be found on the recipe wiki page.

## Phing Tasks
In this repository in the library/PhingTasks/directadmin folder are two tasks can be used to interact with the API of DirectAdmin from within Phing recipes.
The DirectAdminCondition task can be used with Phing's If-task to detect if for example a sub-domain or database exists.
The DirectAdminTask Task can be used to create sub-domains and databases.
These tasks are able to use most of DirectAdmin's API.

Complete documentation of the tasks added to phing by this tool can be found on the [Phing Tasks wiki-page](https://github.com/rbrouwer/dewin/wiki/Phing-Tasks).