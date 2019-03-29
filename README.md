WorkflowExtensionsBundle
========================

[![Build Status](https://travis-ci.org/GlobalTradingTechnologies/workflow-extensions-bundle.svg?branch=master)](https://travis-ci.org/GlobalTradingTechnologies/workflow-extensions-bundle)
[![Coverage Status](https://coveralls.io/repos/github/GlobalTradingTechnologies/workflow-extensions-bundle/badge.svg?branch=master)](https://coveralls.io/github/GlobalTradingTechnologies/workflow-extensions-bundle?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/GlobalTradingTechnologies/workflow-extensions-bundle/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/GlobalTradingTechnologies/workflow-extensions-bundle/?branch=master)
[![Latest Stable Version](https://poser.pugx.org/gtt/workflow-extensions-bundle/version)](https://packagist.org/packages/gtt/workflow-extensions-bundle)
[![Latest Unstable Version](https://poser.pugx.org/gtt/workflow-extensions-bundle/v/unstable)](//packagist.org/packages/gtt/workflow-extensions-bundle)
[![License](https://poser.pugx.org/gtt/workflow-extensions-bundle/license)](https://packagist.org/packages/gtt/workflow-extensions-bundle)

Original Symfony 3 [Workflow component](https://github.com/symfony/workflow) and the [part of Symfony's FrameworkBundle](https://github.com/symfony/symfony/blob/master/src/Symfony/Bundle/FrameworkBundle/DependencyInjection/FrameworkExtension.php#L359-L398) that 
integrates it in Symfony's ecosystem state explicit declaring all the things as a main concept. 
This means that if you need to apply workflow transition you must find target workflow object and call ```$workflow->apply()``` inside your business logic code.
Another example: if you want to block transition you must create a listener that subscribes to GuardEvent and decide whether to block or 
allow transition inside.

But sometimes workflow processes are complex and the code handles transition management grows quickly. 

WorkflowExtensionsBundle provides extensions and additional features to original Symfony 3 Workflow component that can help you to automate some actions you must do manually when you deal with workflow component out of the box:

1. [Event-based transitions triggering](#event-based-transitions-processing)

2. [Event-based transitions scheduling](#event-based-transitions-triggering)

3. [Configurable transition blocking](#event-based-transitions-scheduling)

Requirements
============

Since Symfony's Workflow component requires PHP 5.5.9+ WorkflowExtensionsBundle supports PHP 5.5.9 and newer.

Workflow component is integrated in Symfony 3 ecosystem starting from 3.2 version. In order to use it in applications based on Symfony 3.1 and lower you can use [1.x version](https://github.com/GlobalTradingTechnologies/workflow-extensions-bundle/tree/1.x) of the Bundle.

Besides [symfony/framework-bundle](https://github.com/symfony/framework-bundle) and [symfony/expression-language](https://github.com/symfony/expression-language) packages are required.
 

Installation
============

Bundle should be installed via composer

```
composer require gtt/workflow-extensions-bundle
```
After that you need to register the bundle inside your application kernel:
```php
public function registerBundles()
{
    $bundles = array(
        // ...
        new \Gtt\Bundle\WorkflowExtensionsBundle\WorkflowExtensionsBundle(),
    );
}
```

Configuration and Usage 
=======================

## Workflow subjects
First of all you need to tell WorkflowExtensionsBundle what kind of workflow subjects you want it to deal with.
List it inside `subject_manipulator` section. 
```yml
workflow_extensions:
    subject_manipulator:
        My\Bundle\Entity\Order: ~
        My\Bundle\Entity\Claim: ~
```

## Logging
Since all the things WorkflowExtensionsBundle does are basically automated (and even asynchronous) it is reasonable 
to log important aspects in details. All the WorkflowExtensionsBundle subsystems log (when it is possible) workflow name, subject class and subject id during execution.

There is one non-trivial thing here: how to retrieve subject id from subject. More often subject id can be fetched by invoking `getId()` method, - in this case you have nothing to do.
Otherwise (when your subject class has no `getId()` method or there is the other one should be used to get subject's identifier) you need to specify expression to get subject identifier. This expression will be evaluated by [ExpressionLanguage](https://github.com/symfony/expression-language) component with `subject` variable that represents subject object:
```yaml
workflow_extensions:
    subject_manipulator:
        My\Bundle\Entity\Order: ~
        My\Bundle\Entity\Claim:
            id_from_subject: 'subject.getCustomId()'
```
## Event-based transitions processing
One of the most important use cases of WorkflowExtensionsBundle is to execute some workflow manipulations as a reaction to the particular system events. Any [Symfony's Event](https://github.com/symfony/event-dispatcher/blob/3.1/Event.php) instance can play the role of such firing event.
In order to subscribe workflow processing to such an event you should start with config like this:
```yaml
workflow_extensions:
    workflows:
        simple:
            triggers:
                event:
                    some.event:
                        ...
                    another.event:
                        ... 
        complex:
            triggers:
                event:
                    some.event:
                        ...
                    third.event:
                        ...
    ...                
```
This config firstly specifies target workflow name (`simple`) that should be equal to one of defined workflows in symfony/framework-bundle or fduch/workflow-bundle config.
For each workflow then you define target event and configure processing details as described in sections below.

### Event-based transitions triggering
WorkflowExtensionsBundle makes possible to trigger workflow transitions when particular event is fired.
For example if you want to trigger transition `to_processing` when workflow subject (My\Bundle\Entity\Order instance) is created (order_created.event is fired) the WorkflowExtensionsBundle's config can look like this:  
```yaml
workflow_extensions:
    workflows:
        simple:
            triggers:
                event:
                    order_created.event:
                        actions:
                            apply_transition:
                                - [to_processing]
                        subject_retrieving_expression: 'event.getOrder()'
    subject_manipulator:
        My\Bundle\Entity\Order: ~
```
In example above `subject_retrieving_expression` section contains expression (it will be evaluated by [ExpressionLanguage](https://github.com/symfony/expression-language)) used to retrieve workflow subject.
Since expression language that evaluates these expressions has container variable (represents DI Container) enabled you can construct more complicated things for example like this here: ```"container.get('doctrine').getEntityMangerForClass('My\\\\Bundle\\\\Entity\\\\Order').find('My\\\\Bundle\\\\Entity\\\\Order', event.getId())"``` (Lot of backslashes is set due to specialty of [expression language syntax](http://symfony.com/doc/current/components/expression_language/syntax.html)). 

You can also specify more then one transition to be tried to perform when event is fired by using `apply_transitions` construction like this:
```yaml
    apply_transitions:
        - [[to_processing, closing]]
```
In this case (by default) the first applicable transition would be applied.
You can also consequentially try to apply several transitions without breaking execution after first successfully applied transition using ability to 
invoke `apply_transition` action several times in line with different arguments:
 ```yaml
    apply_transition:
        - [to_processing]
        - [closing]
```

### Event-based transitions scheduling
Events can be used not only to immediately apply transitions, - you can also schedule it with specified offset.
WorkflowExtensionsBundle uses [jms/job-queue-bundle](https://packagist.org/packages/jms/job-queue-bundle) as a scheduler engine.
Imagine you need to apply transition `set_problematic` that places workflow subject `Order` into state "Problematic" if it is not correctly processed in 30 days.
Such goal can be achieved using config like this:  
 
```yaml
workflow_extensions:
    workflows:
        simple:
            triggers:
                event:
                    order_created.event:
                        schedule:
                            apply_transition:
                                -
                                    arguments: [closing]
                                    offset: P30D
                        subject_retrieving_expression: 'event.getOrder()'
    scheduler: ~                
    subject_manipulator:
        My\Bundle\Entity\Order:
            subject_from_domain: "container.get('doctrine').getManagerForClass(subjectClass).find(subjectClass, subjectId)"
    context:
        doctrine: ~
```    
Configuration above is similar to previous one with several differences.

The first difference is that `actions` is replaced with `schedule` key to tell the engine that actions below should be executed deferred.

The second difference is each action's arguments are defined now under explicit `arguments` key (which is automatically set under the hood for [simple triggering](#event-based-transitions-triggering) thanks to [configuration normalization rules](http://symfony.com/doc/current/components/config/definition.html#normalization) and also can be set explicitly there) and
`offset` key that defines time interval (according to [ISO-8601](https://en.wikipedia.org/wiki/ISO_8601#Durations)) started from the moment when corresponding trigger event occurred and after that scheduled transition should be applied.

The third difference is that you need to configure `scheduler` section to activate scheduler engine. Also it can be used to set particular entity manager to persist scheduler jobs.

The fourth difference is that you must configure under `subject_manipulator`'s `subject_from_domain` key expression (it will be evaluated by [ExpressionLanguage](https://github.com/symfony/expression-language)) that will be used to retrieve workflow subject when scheduled transition will be tried to be applied.
The subjectClass (for example My\Bundle\Entity\Order) and subjectId (i.e. identifier you can use to fetch the object) are the expression variables here. Moreover you can use DI container here again since it also registered as expression variable. 

Another feature here is that if you have frequent repeatable event that schedules transition then for the first time when event is fired transition would be simply scheduled and next event occurrences will just reset scheduler countdown to restart it from current moment.
This behaviour can be very useful when you need continuously delay particular transition until specific event is fired regularly. You should not configure something specific to achieve this since this feature is enabled by default.      
## Transition blocking
Basically you can prevent transition from applying explicitly by listening special [GuardEvent](https://github.com/symfony/workflow/blob/master/Event/GuardEvent.php) and call its `setBlocked` method inside. With the help of WorkflowExtensionsBundle you can automate things again.
For example if you to block all the transitions invoked by non-ROLE_USER users and allow only managers (ROLE_MANAGER holders) to apply `dangerous` transition you should use config like this:
```yaml
workflow_extensions:
    workflows:
        simple:
            guard:
                expression: 'not container.get("access_checker").isGranted("ROLE_USER")'
                transitions:
                    dangerous: 'not container.get("access_checker").isGranted("ROLE_MANAGER")'
    subject_manipulator:
        My\Bundle\Entity\Order: ~
    context:
        access_checker: ~
```
Note that here again we use expression evaluated by [ExpressionLanguage](https://github.com/symfony/expression-language) with container variable represents DI Container allowing usage of public services to decide whether to block transitions or not. 

## Contexts
When expressions use some container service it is fetched from container using `container.get()` method. Since Symfony 4 
private services can not be fetched from container in such way. To access required service inside the expression the 
former must be explicitly exposed in bundle configuration. This is done inside `context` array in bundle
configuration:

```yaml
workflow_extensions:
  ...
  context:
    # This will expose "doctrine" service from DI under "doctrine" alias inside expression container
    doctrine: ~ 
    
    # This will expose "security.authorization_checker" from DI and make it available under 
    # "auth_checker" alias inside expression container
    auth_checker: 'security.authorization_checker'
    
  workflows:
    simple:
      guard:
        expression: 'not container.get("auth_checker").isGranted("ROLE_USER")'
        transitions:
          dangerous: 'not container.get("auth_checker").isGranted("ROLE_MANAGER")'
``` 

Tests
=====
WorkflowExtensionsBundle is covered by unit and functional tests. [Functional tests](https://github.com/GlobalTradingTechnologies/workflow-extensions-bundle/tree/master/Tests/Functional) can probably make more clear how the bundle works if you have some misunderstanding.
