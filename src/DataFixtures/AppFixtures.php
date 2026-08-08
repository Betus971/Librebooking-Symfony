<?php

namespace App\DataFixtures;

use App\Entity\Layout;
use App\Entity\ReservationStatus;
use App\Entity\ReservationType;
use App\Entity\Resource;
use App\Entity\ResourceCategory;
use App\Entity\ResourceGroup;
use App\Entity\Schedule;
use App\Entity\TimeBlock;
use App\Entity\User;
use App\Presta\Entity\PlageHoraire;
use App\Presta\Entity\Prestataire;
use App\Presta\Entity\Service;
use App\Presta\Entity\Session;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // ============================================================
        // 1. Données de référence (IDs stables attendus par le code)
        // ============================================================
        foreach ([1 => 'En attente', 2 => 'Confirmée', 3 => 'Refusée', 4 => 'Annulée'] as $id => $label) {
            $status = new ReservationStatus();
            $status->setId($id)->setLabel($label);
            $manager->persist($status);
        }

        $type = new ReservationType();
        $type->setId(ReservationType::STANDARD); // setId() retourne void → pas de chaînage
        $type->setLabel('Standard');
        $manager->persist($type);

        // ============================================================
        // 2. Utilisateurs (mot de passe = identique à la partie locale de l'email)
        // ============================================================
        $admin = $this->makeUser($manager, 'admin@example.com', 'Admin', 'Système', ['ROLE_SUPER_ADMIN'], 'admin');
        $manager_user = $this->makeUser($manager, 'manager@example.com', 'Marie', 'Gestion', ['ROLE_ADMIN_RESSOURCE'], 'manager');
        $this->makeUser($manager, 'user@example.com', 'Jean', 'Dupont', ['ROLE_USER'], 'user');
        $pro = $this->makeUser($manager, 'pro@example.com', 'Camille', 'Pro', ['ROLE_USER', 'ROLE_PRESTATAIRE'], 'pro');

        // ============================================================
        // 3. Grille horaire (Layout) + créneaux d'ouverture
        //    → sans ça, aucune disponibilité ne s'affiche.
        // ============================================================
        $layout = new Layout();
        $layout->setName('Heures de bureau')->setTimezone('Europe/Paris');

        // Lundi (1) → Vendredi (5) : 08h-12h et 14h-18h
        for ($day = 1; $day <= 5; $day++) {
            foreach ([['08:00', '12:00'], ['14:00', '18:00']] as [$start, $end]) {
                $block = new TimeBlock();
                $block->setDayOfWeek($day)
                    ->setAvailabilityCode(TimeBlock::OPEN)
                    ->setStartTime(new \DateTimeImmutable($start))
                    ->setEndTime(new \DateTimeImmutable($end));
                $layout->addTimeBlock($block);
            }
        }
        $manager->persist($layout);

        // ============================================================
        // 4. Planning principal rattaché à la grille
        // ============================================================
        $schedule = new Schedule();
        $schedule->setName('Planning Principal')
            ->setIsDefault(true)
            ->setDaysVisible(7)
            ->setLayout($layout);
        $manager->persist($schedule);

        // ============================================================
        // 5. Catégories de ressources
        // ============================================================
        $catReunion = (new ResourceCategory())->setName('Salles de réunion')->setDescription('Salles pour réunions et visioconférences.');
        $catMateriel = (new ResourceCategory())->setName('Matériel informatique')->setDescription('Prêt d\'ordinateurs, vidéoprojecteurs, etc.');
        $catVehicules = (new ResourceCategory())->setName('Véhicules de service')->setDescription('Voitures et vélos électriques.');
        foreach ([$catReunion, $catMateriel, $catVehicules] as $cat) {
            $manager->persist($cat);
        }

        // ============================================================
        // 6. Groupes d'approbation (le manager gère ces groupes)
        // ============================================================
        $groupeSalles = (new ResourceGroup())->setName('Salles & matériel');
        $groupeSalles->addUser($manager_user);
        $manager->persist($groupeSalles);

        $groupeVehicules = (new ResourceGroup())->setName('Véhicules');
        $groupeVehicules->addUser($manager_user);
        $manager->persist($groupeVehicules);

        // ============================================================
        // 7. Ressources
        // ============================================================
        $resources = [
            ['Salle Alpha (10 places)', $catReunion, $groupeSalles, 10, false],
            ['Salle Beta (20 places)', $catReunion, $groupeSalles, 20, false],
            ['Salle Gamma (5 places)', $catReunion, $groupeSalles, 5, false],
            ['Vidéoprojecteur Epson', $catMateriel, $groupeSalles, 1, false],
            ['PC Portable Dell XPS', $catMateriel, $groupeSalles, 1, false],
            ['MacBook Pro M2', $catMateriel, $groupeSalles, 1, false],
            ['Renault Zoe (Électrique)', $catVehicules, $groupeVehicules, 5, true],
            ['Peugeot 208', $catVehicules, $groupeVehicules, 5, true],
        ];

        foreach ($resources as [$name, $cat, $group, $cap, $requiresApproval]) {
            $resource = new Resource();
            $resource->setName($name)
                ->setCategory($cat)
                ->setResourceGroup($group)
                ->setSchedule($schedule)
                ->setMaxParticipants($cap)
                ->setRequiresApproval($requiresApproval)
                ->setIsActive(true);
            $manager->persist($resource);
        }

        // ============================================================
        // 8. Module Prestations (un prestataire avec services + dispo + séances)
        // ============================================================
        $prestataire = new Prestataire();
        $prestataire->setUser($pro)
            ->setNom('Pro')
            ->setPrenom('Camille')
            ->setDescription('Coach sportif & bien-être. Séances individuelles et cours collectifs.')
            ->setIsActive(true);
        $manager->persist($prestataire);

        $coachingIndiv = (new Service())
            ->setPrestataire($prestataire)
            ->setLibelle('Coaching individuel')
            ->setDescription('Séance personnalisée en tête-à-tête.')
            ->setType(Service::TYPE_INDIVIDUEL)
            ->setDureeMinutes(60)
            ->setCapaciteMax(1)
            ->setIsActive(true);
        $manager->persist($coachingIndiv);

        $coursCollectif = (new Service())
            ->setPrestataire($prestataire)
            ->setLibelle('Cours collectif (Yoga)')
            ->setDescription('Séance de groupe, niveau débutant à intermédiaire.')
            ->setType(Service::TYPE_GROUPE)
            ->setDureeMinutes(60)
            ->setCapaciteMax(8)
            ->setIsActive(true);
        $manager->persist($coursCollectif);

        // Disponibilités types du prestataire (Lun-Ven 09h-12h, 14h-17h)
        for ($day = 1; $day <= 5; $day++) {
            foreach ([['09:00', '12:00'], ['14:00', '17:00']] as [$start, $end]) {
                $plage = (new PlageHoraire())
                    ->setPrestataire($prestataire)
                    ->setJourSemaine($day)
                    ->setHeureDebut(new \DateTimeImmutable($start))
                    ->setHeureFin(new \DateTimeImmutable($end));
                $manager->persist($plage);
            }
        }

        // Quelques séances de groupe à venir
        foreach (['next monday 18:00', 'next wednesday 18:00', 'next friday 12:30'] as $when) {
            $start = new \DateTimeImmutable($when);
            $session = (new Session())
                ->setPrestataire($prestataire)
                ->setService($coursCollectif)
                ->setDateDebut($start)
                ->setDateFin($start->modify('+60 minutes'))
                ->setNbInscrits(0);
            $manager->persist($session);
        }

        $manager->flush();
    }

    /**
     * @param string[] $roles
     */
    private function makeUser(ObjectManager $manager, string $email, string $fname, string $lname, array $roles, string $plainPassword): User
    {
        $user = new User();
        $user->setEmail($email)
            ->setFname($fname)
            ->setLname($lname)
            ->setRoles($roles)
            ->setStatusId(1);
        $user->setPassword($this->hasher->hashPassword($user, $plainPassword));
        $manager->persist($user);

        return $user;
    }
}
