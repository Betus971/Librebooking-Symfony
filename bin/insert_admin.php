<?php

use App\Kernel;

require dirname(__DIR__).'/vendor/autoload.php';

use Symfony\Component\Dotenv\Dotenv;
(new Dotenv())->bootEnv(dirname(__DIR__).'/.env');

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
use App\Entity\User;

$em = $container->get('doctrine')->getManager();
$user = new User();
$user->setEmail('admin@example.com');
$user->setPassword('$2y$13$OfYoBvIfnqLW4waXLviDl.vyb4vJ3CsCAECG2e9yyvr0rTEL/G4Ay');
$user->setFname('Admin');
$user->setLname('System');
$user->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN_RESSOURCE', 'ROLE_SUPER_ADMIN']);

$em->persist($user);
$em->flush();

echo "Admin user created successfully!";
