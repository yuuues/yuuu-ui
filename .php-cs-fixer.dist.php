<?php

declare(strict_types=1);

return (new PhpCsFixer\Config())
    // declare_strict_types is "risky" because it changes runtime
    // behaviour. That is exactly what we want.
    ->setRiskyAllowed(true)
    ->setRules([
        '@Symfony' => true,
        'declare_strict_types' => true,
    ])
    ->setFinder(
        PhpCsFixer\Finder::create()->in([__DIR__.'/src', __DIR__.'/tests'])
    );
