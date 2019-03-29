# Upgrade from 2.x to 3.0

## Moving to PSR-11 container

Under the hood the bundle now uses PSR-11 container implementation for accessing services
inside all expressions. If you implemented `Symfony\Component\DependencyInjection\ContainerAwareInterface`
for actions, you should replace the implementation with `Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ContainerAwareInterface`
and change type-hint to `Psr\Container\ContainerInterface`

Before:
```php
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class MyAction implements ContainerAwareInterface {
  public function setContainer(ContainerInterface $container) {
      // ...
  }
}
```

After:
```php
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ContainerAwareInterface;
use Psr\Container\ContainerInterface;

class MyAction implements ContainerAwareInterface {
  public function setContainer(ContainerInterface $container) {
      // ...
  }
}
```

If `Symfony\Component\DependencyInjection\ContainerAwareTrait` was used just replace the use to
`Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ContainerAwareTrait`.

Before:
```php
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerAwareTrait;

class MyAction implements ContainerAwareInterface {
  use ContainerAwareTrait;
}
```

After:
```php
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ContainerAwareInterface;
use Gtt\Bundle\WorkflowExtensionsBundle\Action\Reference\ContainerAwareTrait;

class MyAction implements ContainerAwareInterface {
  use ContainerAwareTrait;
}
```

## Explicitly exposed services

Prior to symfony 4.0 any service could be fetched from DI container using `$container->get` method. This is
no longer valid. To keep working expressions services must be explicitly defined in bundle configuration:

Before:
```yaml
subject_manipulator:
    Entity\Client:
        subject_from_domain: "container.get('doctrine') ..."
```

After:
```yaml
subject_manipulator:
    Entity\Client:
        subject_from_domain: "container.get('doctrine') ..."
    context:
        doctrine: ~
```
