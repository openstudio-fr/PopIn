<?php

namespace PopIn\Controller;

use PopIn\Hook\Front\FrontHook;
use Thelia\Controller\Front\BaseFrontController;
use Thelia\Core\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PopInFrontController extends BaseFrontController
{
    #[Route('/popin/{popinId}/dismiss', name: 'pop_in_dismiss', methods: ['POST'])]
    public function dismissPopIn($popinId, Request $request)
    {
        $request->getSession()->set(FrontHook::getSeenSessionKeyForPopInCampaign(null, $popinId), true);

        return new Response();
    }
}
