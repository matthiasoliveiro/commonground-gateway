<?php

namespace App\Service;

use App\Entity\Application;
use App\Entity\Gateway;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Security;

class GatewayService
{
    private CommonGroundService $commonGroundService;
    private EntityManagerInterface $entityManager;
    private Security $security;
    private AuthenticationService $authenticationService;
    private RequestStack $requestStack;
    private TranslationService $translationService;

    public function __construct(CommonGroundService $commonGroundService, EntityManagerInterface $entityManager, Security $security, AuthenticationService $authenticationService, RequestStack $requestStack, TranslationService $translationService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->entityManager = $entityManager;
        $this->security = $security;
        $this->authenticationService = $authenticationService;
        $this->requestStack = $requestStack;
        $this->translationService = $translationService;
    }

    /**
     * Processes the call to the Gateway and returns the response.
     *
     * @param string $name     Name of the Gateway.
     * @param string $endpoint Endpoint of the Gateway to send the request to.
     * @param string $method   Method to use against the Gateway.
     * @param string $content  Content to send to the Gateway.
     * @param array  $query    Query parameters to send to the Gateway.
     *
     * @return Response Created response received from Gateway or error received from Gateway.
     */
    public function processGateway(string $name, string $endpoint, string $method, string $content, array $query, array $headers): Response
    {
//        $this->checkAuthentication();
        $gateway = $this->retrieveGateway($name);
        $this->checkGateway($gateway);
        $component = $this->gatewayToArray($gateway);
        $url = $gateway->getLocation().'/'.$endpoint;

        $newHeaders = $gateway->getHeaders();
        $newHeaders['accept'] = $headers['accept'][0];

        //update query params
        if (array_key_exists('query', $gateway->getTranslationConfig())) {
            $query = array_merge($query, $gateway->getTranslationConfig()['query']);
        }

        //translate query params
        foreach ($query as $key => &$value) {
            if (!is_array($value)) {
                $value = $this->translationService->parse($value);
            }
        }

        $result = $this->commonGroundService->callService($component, $url, $content, $query, $newHeaders, false, $method);

        if (is_array($result)) {
            $result['error'] = json_decode($result['error'], true);

            return new Response(
                json_encode($result),
                Response::HTTP_OK,
                ['content-type' => 'application/json']
            );
        }

        return $this->createResponse($result);
    }

    public function checkAuthentication(): void
    {
        $request = $this->requestStack->getCurrentRequest();
        $authorized = true;
        $user = $this->security->getUser();

        $token = str_replace('Bearer ', '', $request->headers->get('Authorization'));

        if (!$user) {
            $authorized = $this->authenticationService->validateJWTAndGetPayload($token, $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'public_key']));
            $authorized = $this->authenticationService->checkJWTExpiration($token);
            $authorized = $this->authenticationService->retrieveJWTUser($token);
        }

        if (!$authorized) {
            throw new AccessDeniedHttpException('Access denied.');
        }
    }

    /**
     * Creates Response object based on the guzzle response.
     *
     * @param object $result The object returned from guzzle.
     *
     * @return Response Created response object.
     */
    public function createResponse(object $result): Response
    {
        $response = new Response();
        $response->setContent($result->getBody()->getContents());
        $response->headers->replace($result->getHeaders());
        $headers = $result->getHeaders();
        $response = $this->handleCorsHeader($response);
        $response->headers->remove('Server');
        $response->headers->remove('X-Content-Type-Options');
        $response->headers->remove('Set-Cookie');
        $response->setStatusCode($result->getStatusCode());

        return $response;
    }

    public function handleCorsHeader(Response $response): Response
    {
        $request = $this->requestStack->getCurrentRequest();
        $host = $request->headers->get('host');

        $applications = $this->entityManager->getRepository('App:Application')->findAll();
        $applications = array_values(array_filter($applications, function (Application $application) use ($host) {
            return in_array($host, $application->getDomains());
        }));

        if (count($applications) > 0) {
            $response->headers->add(['access-control-allow-origin' => $request->headers->get('Origin')]);
        } else {
            $response->headers->remove('access-control-allow-origin');
        }

        return $response;
    }

    /**
     * Creates array from Gateway object to be used by common ground service.
     *
     * @param Gateway $gateway The Gateway object.
     *
     * @return array Created array from the Gateway object.
     */
    public function gatewayToArray(Gateway $gateway): array
    {
        $result = [
            'auth'                  => $gateway->getAuth(),
            'authorizationHeader'   => $gateway->getAuthorizationHeader(),
            'passthroughMethod'     => $gateway->getAuthorizationPassthroughMethod(),
            'location'              => $gateway->getLocation(),
            'apikey'                => $gateway->getApiKey(),
            'jwt'                   => $gateway->getJwt(),
            'secret'                => $gateway->getSecret(),
            'id'                    => $gateway->getJwtId(),
            'locale'                => $gateway->getLocale(),
            'accept'                => $gateway->getAccept(),
            'username'              => $gateway->getUsername(),
            'password'              => $gateway->getPassword(),
        ];

        return array_filter($result);
    }

    /**
     * Checks if the Gateway object is valid.
     *
     * @param Gateway $gateway The Gateway object that needs to be checked.
     *
     * @throws BadRequestHttpException If the Gateway object is not valid.
     */
    public function checkGateway(Gateway $gateway): void
    {
        switch ($gateway->getAuth()) {
            case 'jwt':
                if ($gateway->getJwtId() == null || $gateway->getSecret() == null) {
                    throw new BadRequestHttpException('jwtid and secret are required for auth type: jwt');
                }
                break;
            case 'apikey':
                if ($gateway->getApiKey() == null) {
                    throw new BadRequestHttpException('ApiKey is required for auth type: apikey');
                }
                break;
            case 'username-password':
                if ($gateway->getUsername() == null || $gateway->getPassword() == null) {
                    throw new BadRequestHttpException('Username and password are required for auth type: username-password');
                }
                break;
        }
    }

    /**
     * Tries to retrieve the Gateway object with entity manager.
     *
     * @param $gateway string Name of the Gateway used to search for the object.
     *
     * @throws NotFoundHttpException If there is no Gateway object found with the provided name.
     *
     * @return Gateway The retrieved Gateway object.
     */
    public function retrieveGateway(string $gateway): Gateway
    {
        if (strpos($gateway, '.') && $renderType = explode('.', $gateway)) {
            $gateway = $renderType[0];
        }

        $gateways = $this->entityManager->getRepository('App\Entity\Gateway')->findBy(['name' => $gateway]);

        if (count($gateways) == 0 || !$gateways[0] instanceof Gateway) {
            throw new NotFoundHttpException('Unable to find Gateway');
        }

        return $gateways[0];
    }

    /**
     * Turns the gateway response into a downloadable file.
     *
     * @param Response $response  The response to turn into a download.
     * @param string   $extension The extension of the requested file e.g. csv
     *
     * @return Response The retrieved Gateway object.
     */
    public function retrieveExport(Response $response, $extension, $fileName): Response
    {
        $content = $response->getContent();
        switch ($response->headers->Get('content-type')) {
            case 'application/json+ld':
            case 'application/ld+json':
            case 'application/json':
                $content = json_decode($content, true);
                // Lets deal with an results array
                if (array_key_exists('results', $content)) {
                    $content = $content['resuts'];
                }
                break;
            case 'application/hal+json':
            case 'application/json+hal':
                $content = json_decode($content, true);
                $content = $content['items'];
                break;
            case 'application/xml':
                break;
            default:
                // @todo throw unsuported type error
        }

        // @todo deze array is dubbel met de EavService
        $acceptHeaderToSerialiazation = $this->acceptHeaderToSerialiazation();

        $contentType = array_search($extension, $acceptHeaderToSerialiazation);

        $date = new \DateTime();
        $date = $date->format('Ymd_His');
        $disposition = $response->headers->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, "{$fileName->getName()}_{$date}.{$extension}");
        $response->headers->set('Content-Disposition', $disposition);
        $response->headers->set('content-type', $contentType);

        return $response;
    }

    /**
     * Returns the array with accept headers.
     *
     * @return string[]
     */
    private function acceptHeaderToSerialiazation(): array
    {
        return [
            'application/json'     => 'json',
            'application/ld+json'  => 'jsonld',
            'application/json+ld'  => 'jsonld',
            'application/hal+json' => 'jsonhal',
            'application/json+hal' => 'jsonhal',
            'application/xml'      => 'xml',
            'text/csv'             => 'csv',
            'text/yaml'            => 'yaml',
        ];
    }
}
