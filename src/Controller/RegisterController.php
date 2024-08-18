<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route("/api", defaults: ["_format" => "json"])]
class RegisterController extends AbstractController
{
    #[Route('/register', name: 'app_register', methods: "POST")]
    public function register(Request $request, EntityManagerInterface $em, UserPasswordHasherInterface $passwordHasher, ValidatorInterface $validator)
    {
        if ($this->getUser()) {
            return $this->json(["error" => "User is authenticated"]);
        }
        $data = $request->toArray();
        $user = new User();
        $hashedPassword = $passwordHasher->hashPassword($user, $data["password"]);
        $user
            ->setEmail($data["username"])
            ->setPassword($data["password"]);
        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            $messages["errors"] = [];
            foreach ($errors as $error) {
                $messages["errors"][$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json($messages, Response::HTTP_BAD_REQUEST);
        }
        $user->setPassword($hashedPassword);
        $em->persist($user);
        $em->flush();
        return $this->json(["user" => $user]);


    }
}
