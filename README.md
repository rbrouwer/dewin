Dewin
===========

Current Build Status:
[![Build Status](https://secure.travis-ci.org/rbrouwer/dewin.png)](http://travis-ci.org/rbrouwer/dewin)

Dewin(DEployment Web INterface or Deinstall Windows or (in Dutch) De Enige Waardeloze INstaller or ...) is a tool that Schuttelaar & Partners is developing to make deploying of their web-applications easier.

It is a front end for Phing, with some business logic to make deployments a lot easier.

A lot of documentation will follow to make it possible to actually use it (to its full potential).


In this repository in the library/PhingTasks/directadmin folder are two tasks can be used to interact with the API of DirectAdmin from within Phing recipes.
The DirectAdminCondition task can be used with Phing's If-task to detect if for example a sub-domain or database exists.
The DirectAdminTask Task can be used to create sub-domains and databases.
These tasks are able to use most of DirectAdmin's API.