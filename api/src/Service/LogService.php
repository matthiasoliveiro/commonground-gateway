<?php

namespace App\Service;

use App\Entity\Log;
use Doctrine\ORM\EntityManagerInterface;
use ReflectionClass;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class LogService
{
    private EntityManagerInterface $entityManager;
    private SessionInterface $session;

    public function __construct(
        EntityManagerInterface $entityManager,
        SessionInterface $session
    ) {
        $this->entityManager = $entityManager;
        $this->session = $session;
    }

    /**
     * Creates or updates a Log object with current request and response or given content.
     *
     * @param Request       $request   The request to fill this Log with.
     * @param Response|null $response  The response to fill this Log with.
     * @param string|null   $content   The content to fill this Log with if there is no response.
     * @param bool|null     $finalSave
     * @param string        $type
     *
     * @return Log
     */
    public function saveLog(Request $request, Response $response = null, string $content = null, bool $finalSave = null, string $type = 'in'): Log
    {
        $logRepo = $this->entityManager->getRepository('App:Log');

        $this->session->get('callId') !== null && $type == 'in' ? $existingLog = $logRepo->findOneBy(['callId' => $this->session->get('callId'), 'type' => $type]) : $existingLog = null;

        $existingLog ? $callLog = $existingLog : $callLog = new Log();

        $callLog->setType($type);
        $callLog->setRequestMethod($request->getMethod());
        $callLog->setRequestHeaders($request->headers->all());
        $callLog->setRequestQuery($request->query->all() ?? null);
        $callLog->setRequestPathInfo($request->getPathInfo());
        $callLog->setRequestLanguages($request->getLanguages() ?? null);
        $callLog->setRequestServer($request->server->all());
        $callLog->setRequestContent($request->getContent());
        $response && $callLog->setResponseStatus($this->getStatusWithCode($response->getStatusCode()));
        $response && $callLog->setResponseStatusCode($response->getStatusCode());
        $response && $callLog->setResponseHeaders($response->headers->all());

        if ($content) {
            $callLog->setResponseContent($content);
        // @todo Cant set response content if content is pdf
        } elseif ($response && !(is_string($response->getContent()) && strpos($response->getContent(), 'PDF'))) {
            $callLog->setResponseContent($response->getContent());
        }

        $routeName = $request->attributes->get('_route') ?? null;
        $routeParameters = $request->attributes->get('_route_params') ?? null;
        $callLog->setRouteName($routeName);
        $callLog->setRouteParameters($routeParameters);

        $time = microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'];
        $callLog->setResponseTime(intval($time * 1000));

        $callLog->setCreatedAt(new \DateTime());

        if ($this->session) {
            // add before removing
            $callLog->setCallId($this->session->get('callId'));
            $callLog->setSession($this->session->getId());

            if ($this->session->get('endpoint')) {
                $endpoint = $this->entityManager->getRepository('App:Endpoint')->findOneBy(['id' => $this->session->get('endpoint')->getId()->toString()]);
            }
            $callLog->setEndpoint(!empty($endpoint) ? $endpoint : null);
            if ($this->session->get('entity')) {
                $entity = $this->entityManager->getRepository('App:Entity')->findOneBy(['id' => $this->session->get('entity')->getId()->toString()]);
            }
            $callLog->setEntity(!empty($entity) ? $entity : null);
            if ($this->session->get('source')) {
                $source = $this->entityManager->getRepository('App:Gateway')->findOneBy(['id' => $this->session->get('source')->getId()->toString()]);
            }
            $callLog->setGateway(!empty($source) ? $source : null);
            if ($this->session->get('handler')) {
                $handler = $this->entityManager->getRepository('App:Handler')->findOneBy(['id' => $this->session->get('handler')->getId()]);
            }
            $callLog->setHandler(!empty($handler) ? $handler : null);

            // remove before setting the session values
//            if ($finalSave === true) {
//                $this->session->remove('callId');
//                $this->session->remove('endpoint');
//                $this->session->remove('entity');
//                $this->session->remove('source');
//                $this->session->remove('handler');
//            }

            // Set session values without relations we already know
            // $sessionValues = $this->session->all();
            // unset($sessionValues['endpoint']);
            // unset($sessionValues['source']);
            // unset($sessionValues['entity']);
            // unset($sessionValues['endpoint']);
            // unset($sessionValues['handler']);
            // unset($sessionValues['application']);
            // unset($sessionValues['applications']);
            // $callLog->setSessionValues($sessionValues);
        }
        $this->entityManager->persist($callLog);
        $this->entityManager->flush();

        return $callLog;
    }

    public function makeRequest(): Request
    {
        return new Request(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER
        );
    }

    public function getStatusWithCode(int $statusCode): ?string
    {
        $reflectionClass = new ReflectionClass(Response::class);
        $constants = $reflectionClass->getConstants();

        foreach ($constants as $status => $value) {
            if ($value == $statusCode) {
                return $status;
            }
        }

        return null;
    }
}
