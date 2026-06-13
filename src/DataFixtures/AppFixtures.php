<?php

namespace App\DataFixtures;

use App\Entity\Resource;
use App\Entity\ResourceCategory;
use App\Entity\Schedule;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        // 1. Création d'un planning principal
        $schedule = new Schedule();
        $schedule->setName('Planning Principal');
        $schedule->setIsDefault(true);
        $schedule->setDaysVisible(7);
        $manager->persist($schedule);

        // 2. Création de catégories de ressources
        $catReunion = new ResourceCategory();
        $catReunion->setName('Salles de réunion');
        $catReunion->setDescription('Salles pour réunions et visioconférences.');
        $manager->persist($catReunion);

        $catMateriel = new ResourceCategory();
        $catMateriel->setName('Matériel Informatique');
        $catMateriel->setDescription('Prêt d\'ordinateurs, vidéoprojecteurs, etc.');
        $manager->persist($catMateriel);

        $catVehicules = new ResourceCategory();
        $catVehicules->setName('Véhicules de service');
        $catVehicules->setDescription('Voitures et vélos électriques.');
        $manager->persist($catVehicules);

        // 3. Création des ressources (Salles)
        $salles = [
            ['name' => 'Salle Alpha (10 places)', 'max_participants' => 10, 'cat' => $catReunion],
            ['name' => 'Salle Beta (20 places)', 'max_participants' => 20, 'cat' => $catReunion],
            ['name' => 'Salle Gamma (5 places)', 'max_participants' => 5, 'cat' => $catReunion],
        ];

        foreach ($salles as $salleData) {
            $resource = new Resource();
            $resource->setName($salleData['name']);
            $resource->setCategory($salleData['cat']);
            $resource->setSchedule($schedule);
            $resource->setMaxParticipants($salleData['max_participants']);
            $resource->setIsActive(true);
            $manager->persist($resource);
        }

        // Matériels
        $materiels = [
            ['name' => 'Vidéoprojecteur Epson', 'cat' => $catMateriel],
            ['name' => 'PC Portable Dell XPS', 'cat' => $catMateriel],
            ['name' => 'MacBook Pro M2', 'cat' => $catMateriel],
        ];

        foreach ($materiels as $matData) {
            $resource = new Resource();
            $resource->setName($matData['name']);
            $resource->setCategory($matData['cat']);
            $resource->setSchedule($schedule);
            $resource->setIsActive(true);
            $manager->persist($resource);
        }

        // Véhicules
        $vehicules = [
            ['name' => 'Renault Zoe (Électrique)', 'cat' => $catVehicules],
            ['name' => 'Peugeot 208', 'cat' => $catVehicules],
        ];

        foreach ($vehicules as $vehData) {
            $resource = new Resource();
            $resource->setName($vehData['name']);
            $resource->setCategory($vehData['cat']);
            $resource->setSchedule($schedule);
            $resource->setIsActive(true);
            $resource->setRequiresApproval(true); // Exemple: Nécessite approbation
            $manager->persist($resource);
        }

        $manager->flush();
    }
}
