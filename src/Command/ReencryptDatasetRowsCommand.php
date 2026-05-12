<?php

namespace App\Command;

use App\Entity\DatasetRow;
use App\Doctrine\Type\EncryptedJsonType;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:reencrypt-dataset-rows', description: 'Rewrite dataset rows so JSON payloads are encrypted at rest')]
class ReencryptDatasetRowsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly Connection $connection,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->em->getRepository(DatasetRow::class)->findAll();
        $type = new EncryptedJsonType();
        $platform = $this->connection->getDatabasePlatform();
        $count = 0;

        foreach ($rows as $row) {
            $this->connection->update('dataset_row', [
                'raw_data' => $type->convertToDatabaseValue($row->getRawData(), $platform),
                'normalized_data' => $type->convertToDatabaseValue($row->getNormalizedData(), $platform),
            ], [
                'id' => $row->getId(),
            ]);
            $count++;
        }
        $output->writeln(sprintf('Filas reescritas con cifrado: %d', $count));

        return Command::SUCCESS;
    }
}
