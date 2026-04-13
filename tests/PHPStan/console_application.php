<?php

declare(strict_types=1);

use Acme\SyliusExamplePlugin\Tests\Application\Kernel;
use Symfony\Bundle\FrameworkBundle\Console\Application;

require __DIR__ . '/../Application/config/bootstrap.php';

$kernel = new Kernel('test', true);
$kernel->boot();

return new Application($kernel);
