<?php

namespace App\Controller;

use App\Entity\Client;
use App\Service\CustomerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/account/customer')]
class CostumerController extends AbstractController
{
    #[Route('/', name: 'app_client_index')]
    public function index(CustomerService $customerService,EntityManagerInterface $entityManager): Response
    {
        return $customerService->index($entityManager->getRepository(Client::class));
    }

    #[Route('/new', name: 'app_client_new', methods: ['GET', 'POST'])]
    public function new(Request $request, CustomerService $customerService): Response
    {
        return $customerService->new($request);
    }

    #[Route('/details/{id}', name: 'client_show', methods: ['GET'])]
    public function show(Client $client, CustomerService $customerService): Response
    {
        return $customerService->show($client);
    }

    #[Route('/{id}/edit', name: 'client_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Client $client, CustomerService $customerService): Response
    {
        return $customerService->edit($request, $client);
    }

    #[Route('/{id}', name: 'client_delete', methods: ['POST'])]
    public function delete(Request $request, Client $client, CustomerService $customerService): Response
    {
        return $customerService->delete($request, $client);
    }
}
