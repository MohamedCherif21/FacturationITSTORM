<?php

namespace App\Service;

use App\Entity\Client;
use App\Form\ClientType;
use App\Repository\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

class CustomerService
{
    private $entityManager;
    private $formFactory;
    private $urlGenerator;
    private $twig;
    private $csrfTokenManager;
    private $clientRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        FormFactoryInterface $formFactory,
        UrlGeneratorInterface $urlGenerator,
        \Twig\Environment $twig,
        CsrfTokenManagerInterface $csrfTokenManager,
        ClientRepository $clientRepository
    ) {
        $this->entityManager = $entityManager;
        $this->formFactory = $formFactory;
        $this->urlGenerator = $urlGenerator;
        $this->twig = $twig;
        $this->csrfTokenManager = $csrfTokenManager;
        $this->clientRepository = $clientRepository;
    }

    public function index(): Response
    {
        $clients = $this->clientRepository->findAll();
        return new Response($this->render('client/index.html.twig', [
            'clients' => $clients,
        ]));
    }

    public function new(Request $request): Response
    {
        $client = new Client();
        $form = $this->formFactory->create(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($client);
            $this->entityManager->flush();

            return new Response($this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER));
        }

        return new Response($this->render('client/new.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]));
    }

    public function show(int $id): Response
    {
        $client = $this->clientRepository->find($id);
        if (!$client) {
            throw $this->createNotFoundException('Client not found');
        }

        return new Response($this->render('client/show.html.twig', [
            'client' => $client,
            'factures' => $client->getFactures(),
        ]));
    }

    public function edit(Request $request, int $id): Response
    {
        $client = $this->clientRepository->find($id);
        if (!$client) {
            throw $this->createNotFoundException('Client not found');
        }

        $form = $this->formFactory->create(ClientType::class, $client);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            return new Response($this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER));
        }

        return new Response($this->render('client/edit.html.twig', [
            'client' => $client,
            'form' => $form->createView(),
        ]));
    }

    public function delete(Request $request, int $id): Response
    {
        $client = $this->clientRepository->find($id);
        if (!$client) {
            throw $this->createNotFoundException('Client not found');
        }

        if ($this->isCsrfTokenValid('delete' . $client->getId(), $request->request->get('_token'))) {
            $this->entityManager->remove($client);
            $this->entityManager->flush();
        }

        return new Response($this->redirectToRoute('app_client_index', [], Response::HTTP_SEE_OTHER));
    }

    private function render(string $template, array $parameters): string
    {
        return $this->twig->render($template, $parameters);
    }

    private function redirectToRoute(string $route, array $parameters = [], int $status = 302): Response
    {
        return new RedirectResponse($this->urlGenerator->generate($route, $parameters), $status);
    }

    private function isCsrfTokenValid(string $id, ?string $token): bool
    {
        return $this->csrfTokenManager->isTokenValid(new CsrfToken($id, $token));
    }

    private function createNotFoundException(string $message): \Exception
    {
        return new \Exception($message, Response::HTTP_NOT_FOUND);
    }
}