# Fregata Bundle

**Symfony** bundle for the [Fregata](https://github.com/AymDev/Fregata) data migration framework. Provides an UI and
executes migrations asynchronously using the **Messenger** component.

>Work in progress !

**Documentation**:

1. [Requirements](#requirements)
2. [Installation](#installation)

# Requirements
As **Fregata**, the bundle requires **PHP 7.4+**.
At the **Symfony** level, you need a **Symfony 4.4+** application. The bundle also requires the **Doctrine** *bundle*
and *ORM*, and **Symfony**'s *Messenger* component.

# Installation
Install with **Composer**:
```shell
composer require aymdev/fregata-bundle
```

Then you will need to create the database tables for the provided entities (3 entities + a *ManyToMany* relation). You 
can do this how you want.
>**Suggestion:** My preferred way to use database migrations is by using the 
> [MakerBundle](https://symfony.com/doc/current/bundles/SymfonyMakerBundle/index.html) and its `make:migration` command 
> followed by **Doctrine**'s `doctrine:migrations:migrate` command.

As the main work of the bundle happens in *Messenger* components, you need to *route* the provided **messages** to a 
**transport** of your choice.
Example **config/packages/messenger.yaml**:
```yaml
framework:
    messenger:
        transports:
            # You are entirely responsible for the transport configuration
            async: '%env(MESSENGER_TRANSPORT_DSN)%'

        routing:
            # Every message implements the following interface, nothing more is needed
            'Fregata\FregataBundle\Messenger\FregataMessageInterface': async
```
