<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\HttpService;
use Hyperf\Di\Annotation\Inject;

class IndexController extends AbstractController
{
    #[Inject]
    protected HttpService $service;

    public function index()
    {
        return $this->service->handle($this->request->post());
    }
}
