<?php

namespace App\Repository;

use App\Entity\Setting;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Setting>
 */
class SettingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Setting::class);
    }

    /** null = Einstellung wurde noch nie gespeichert */
    public function getValue(string $name): ?Setting
    {
        return $this->find($name);
    }

    public function setValue(string $name, ?string $value): void
    {
        $setting = $this->find($name);
        if (null === $setting) {
            $this->getEntityManager()->persist(new Setting($name, $value));
        } else {
            $setting->setValue($value);
        }
        $this->getEntityManager()->flush();
    }
}
