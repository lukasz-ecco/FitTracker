<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\UserType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/user/info')]
final class UserInfoController extends AbstractController
{
    #[Route('/edit', name: 'app_user_info_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $form = $this->createForm(UserType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avatarFile = $form->get('profilePicture')->getData();
            if ($avatarFile) {
                $newFilename = uniqid() . '.' . $avatarFile->guessExtension();
                try{
                    $avatarFile->move($this->getParameter('avatars_directory'), $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Nie można przesłać zdjęcia: ' . $e->getMessage());
                }   
                $user->setProfilePicture($newFilename);
            }

            $entityManager->flush();
            $this->addFlash('success', 'Twój profil został zaktualizowany.');
        }

        return $this->render('user_info/edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
