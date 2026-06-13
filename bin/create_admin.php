<?php

use App\Kernel;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

require dirname(__DIR__).'/vendor/autoload.php';

$kernel = new Kernel('dev', true);
$kernel->boot();

$container = $kernel->getContainer();
/** @var EntityManagerInterface $em */
$em = $container->get('doctrine')->getManager();
/** @var UserPasswordHasherInterface $hasher */
$hasher = $container->get('security.password_hasher');

$email = 'admin@example.com';

$user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
if (!$user) {
    $user = new User();
    $user->setEmail($email);
    $user->setFname('Admin');
    $user->setLname('System');
    $user->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN_RESSOURCE']);
    
    $hashedPassword = $hasher->hashPassword($user, 'admin123');
    $user->setPassword($hashedPassword);
    
    $em->persist($user);
    $em->flush();
    echo "User created: admin@example.com / admin123\n";
} else {
    $user->setRoles(['ROLE_ADMIN', 'ROLE_ADMIN_RESSOURCE']);
    $hashedPassword = $hasher->hashPassword($user, 'admin123');
    $user->setPassword($hashedPassword);
    $em->flush();
    echo "User updated: admin@example.com / admin123\n";
}
