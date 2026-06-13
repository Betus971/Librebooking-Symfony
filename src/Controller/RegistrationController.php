<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $errors = [];
        $email = '';
        $fname = '';
        $lname = '';

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            $password = $request->request->get('password', '');
            $fname = trim($request->request->get('fname', ''));
            $lname = trim($request->request->get('lname', ''));

            // Simple validation
            $emailConstraint = new Assert\Email();
            $emailConstraint->message = 'L\'adresse email est invalide.';
            
            $inputErrors = $validator->validate($email, [
                new Assert\NotBlank(['message' => 'L\'adresse email est obligatoire.']),
                $emailConstraint
            ]);

            if (count($inputErrors) > 0) {
                foreach ($inputErrors as $err) {
                    $errors[] = $err->getMessage();
                }
            }

            if (empty($password) || strlen($password) < 6) {
                $errors[] = 'Le mot de passe doit faire au moins 6 caractères.';
            }

            if (empty($fname)) {
                $errors[] = 'Le prénom est obligatoire.';
            }

            if (empty($lname)) {
                $errors[] = 'Le nom est obligatoire.';
            }

            // Check if email already exists
            $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existingUser) {
                $errors[] = 'Cette adresse email est déjà utilisée.';
            }

            if (empty($errors)) {
                $user = new User();
                $user->setEmail($email);
                $user->setFname($fname);
                $user->setLname($lname);
                $user->setRoles(['ROLE_USER']);
                $user->setStatusId(1); // Active
                $user->setTimezone('Europe/Paris');
                $user->setLanguage('fr');
                $user->setHomepageid(1);

                // Hash password
                $user->setPassword(
                    $userPasswordHasher->hashPassword(
                        $user,
                        $password
                    )
                );

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('registration/register.html.twig', [
            'errors' => $errors,
            'email' => $email,
            'fname' => $fname,
            'lname' => $lname,
        ]);
    }
}
