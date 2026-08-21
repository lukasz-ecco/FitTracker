<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class UserProfileService
{
    public function __construct(
        private EntityManagerInterface $em,
        private ParameterBagInterface $params
    ) {}

    public function updateProfile(User $user, array $data): bool
    {
        $updatableFields = [
            'age' => 'setAge',
            'gender' => 'setGender',
            'height' => 'setHeight',
            'weight' => 'setWeight',
        ];

        $updated = false;
        foreach ($updatableFields as $field => $setter) {
            if (array_key_exists($field, $data)) {
                $user->$setter($data[$field]);
                $updated = true;
            }
        }

        if (array_key_exists('profilePictureBase64', $data) && !empty($data['profilePictureBase64'])) {
            $base64Data = $data['profilePictureBase64'];
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $type = strtolower($type[1]);
                
                if ($type === 'jpeg') {
                    $type = 'jpg';
                }
                
                $allowedExtensions = ['jpg', 'png', 'gif', 'webp'];
                if (!in_array($type, $allowedExtensions)) {
                    throw new \InvalidArgumentException('Nieprawidłowy format zdjęcia. Dozwolone: JPG, PNG, GIF, WEBP.');
                }
                
                $decodedData = base64_decode($base64Data);
                if ($decodedData === false) {
                    throw new \InvalidArgumentException('Nie udało się zdekodować zdjęcia.');
                }
                
                $fileName = uniqid() . '.' . $type;
                $uploadDir = $this->params->get('avatars_directory');
                
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }
                
                file_put_contents($uploadDir . '/' . $fileName, $decodedData);
                $user->setProfilePicture($fileName);
                $updated = true;
            } else {
                throw new \InvalidArgumentException('Nieprawidłowy format base64 zdjęcia. Musi zawierać prefix data:image/...');
            }
        }

        if ($updated) {
            $this->em->flush();
        }

        return $updated;
    }
    
    public function getUpdatableFields(): array
    {
        return ['age', 'gender', 'height', 'weight', 'profilePictureBase64'];
    }
}
