<?php

/*
 * Copyright 2020 BibLibre
 *
 * This file is part of CAS.
 *
 * CAS is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * CAS is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with CAS.  If not, see <https://www.gnu.org/licenses/>.
 */

namespace CAS\Controller;

use CAS\Entity\CasUser;
use Doctrine\ORM\EntityManager;
use Omeka\Entity\User;
use Omeka\Permissions\Acl;
use Laminas\Authentication\AuthenticationService;
use Laminas\Http\Client as HttpClient;
use Laminas\Mvc\Controller\AbstractActionController;
use Laminas\Session\Container;

class LoginController extends AbstractActionController
{
    protected $httpClient;
    protected $entityManager;
    protected $authenticationService;

    public function __construct(HttpClient $httpClient, EntityManager $entityManager, AuthenticationService $authenticationService)
    {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->authenticationService = $authenticationService;
    }

    public function loginAction ()
    {
        $ticket = $this->params()->fromQuery('ticket');
        if (!isset($ticket)) {
            $redirectUrl = $this->params()->fromQuery('redirect_url');
            if (isset($redirectUrl)) {
                $session = Container::getDefaultManager()->getStorage();
                $session->offsetSet('redirect_url', $redirectUrl);
            }

            $casUrl = sprintf(
                '%s/login?service=%s',
                $this->settings()->get('cas_url'),
                $this->url()->fromRoute('cas/login', [], ['force_canonical' => true])
            );
            return $this->redirect()->toUrl($casUrl);
        }

        $response = $this->serviceValidate($this->params()->fromQuery('ticket'));
        if (!$response->isOk()) {
            $this->messenger()->addError($this->translate('Failed to validate CAS ticket'));
            return $this->redirect()->toRoute('login');
        }

        $body = $response->getBody();
        $xml = simplexml_load_string($body, 'SimpleXMLElement', 0, 'http://www.yale.edu/tp/cas');
        $casResponse = json_decode(json_encode($xml), true);
        if (!isset($casResponse['authenticationSuccess'])) {
            $this->messenger()->addError($this->translate('Failed to validate CAS ticket'));
            return $this->redirect()->toRoute('login');
        }

        $cas = $casResponse['authenticationSuccess'];

        $user = $this->getUser($cas);
        if (!$user->isActive()) {
            $this->messenger()->addError($this->translate('User is inactive'));
            return $this->redirect()->toRoute('login');
        }

        $sessionManager = Container::getDefaultManager();
        $sessionManager->regenerateId();

        $this->authenticationService->getStorage()->write($user);
        $this->getEventManager()->trigger('cas.user.login', $user, [
            'user' => $cas['user'],
            'attributes' => $cas['attributes'] ?? [],
        ]);

        $session = $sessionManager->getStorage();
        if ($redirectUrl = $session->offsetGet('redirect_url')) {
            return $this->redirect()->toUrl($redirectUrl);
        }

        return $this->redirect()->toRoute('admin');
    }

    protected function serviceValidate($ticket)
    {
        $serviceValidateUrl = $this->settings()->get('cas_url') . '/serviceValidate';
        $httpClient = $this->httpClient;
        $httpClient->reset();
        $httpClient->setUri($serviceValidateUrl);
        $httpClient->setParameterGet([
            'service' => $this->url()->fromRoute('cas/login', [], ['force_canonical' => true]),
            'ticket' => $ticket,
        ]);

        return $httpClient->send();
    }

    protected function getUser($cas)
    {
        $em = $this->entityManager;
        $events = $this->getEventManager();
        $eventArgs = [
            'user' => $cas['user'],
            'attributes' => $cas['attributes'] ?? [],
        ];

        $casUser = $em->find('CAS\Entity\CasUser', $cas['user']);
        if (!$casUser) {
            $user = new User();
            $user->setName($cas['user']);
            $user->setEmail($cas['user']);
            $user->setRole($this->settings()->get('cas_role', Acl::ROLE_RESEARCHER));
            $user->setIsActive(true);

            $casUser = new CasUser();
            $casUser->setId($cas['user']);
            $casUser->setUser($user);

            $events->trigger('cas.user.create.pre', $user, $eventArgs);

            $em->persist($user);
            $em->persist($casUser);
            $em->flush();

            $events->trigger('cas.user.create.post', $user, $eventArgs);
        } else {
            $user = $casUser->getUser();

            $events->trigger('cas.user.update.pre', $user, $eventArgs);

            $em->persist($user);
            $em->flush();

            $events->trigger('cas.user.update.post', $user, $eventArgs);
        }

        return $user;
    }
}
