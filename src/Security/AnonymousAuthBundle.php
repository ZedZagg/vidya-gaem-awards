<?php

namespace App\Security;

use Symfony\Bundle\SecurityBundle\DependencyInjection\SecurityExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * This class (and the associated factory) exist to work around Symfony's default behaviour around authenticator priority.
 *
 * By default, any custom authenticators (as defined in the custom_authenticators section of the security config) are
 * run before any Symfony-provided authenticators. This includes the RememberMeAuthenticator.
 *
 * For 99% of use cases, this is fine, but it poses a problem for us, because one of the custom authenticators we have
 * is the AnonymousAuthenticator, which generates a random ID for logged-out users and stores it in a cookie.
 * We then treat this logged-out user as a psuedo-User, but because this creates a token for Symfony to use,
 * the RememberMeAuthenticator will not run.
 *
 * The only way to avoid this is to use our own AnonyousAuthFactory which runs at a lower priority compared to the
 * RememberMeAuthenticator, and then register it using our own bundle.
 */
class AnonymousAuthBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        /** @var SecurityExtension $extension */
        $extension = $container->getExtension('security');

        $extension->addAuthenticatorFactory(new AnonymousAuthFactory());
    }
}
