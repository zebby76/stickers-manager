<?php

namespace App\Command;

use App\Repository\UserRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:user:admin',
    description: 'Grant (or revoke with --revoke) ROLE_ADMIN to a user by e-mail.',
)]
class PromoteUserCommand extends Command
{
    public function __construct(private readonly UserRepository $users)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, "User's e-mail address")
            ->addOption('revoke', null, InputOption::VALUE_NONE, 'Revoke ROLE_ADMIN instead of granting it');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');
        $revoke = (bool) $input->getOption('revoke');

        $user = $this->users->findOneBy(['email' => $email]);
        if ($user === null) {
            $io->error(sprintf('No user found with e-mail "%s".', $email));

            return Command::FAILURE;
        }

        $roles = array_values(array_filter($user->getRoles(), static fn (string $r) => $r !== 'ROLE_USER'));

        if ($revoke) {
            $roles = array_values(array_filter($roles, static fn (string $r) => $r !== 'ROLE_ADMIN'));
            $message = sprintf('ROLE_ADMIN revoked from %s.', $email);
        } else {
            if (!\in_array('ROLE_ADMIN', $roles, true)) {
                $roles[] = 'ROLE_ADMIN';
            }
            $message = sprintf('ROLE_ADMIN granted to %s.', $email);
        }

        $user->setRoles($roles);
        $this->users->save($user);

        $io->success($message);

        return Command::SUCCESS;
    }
}
