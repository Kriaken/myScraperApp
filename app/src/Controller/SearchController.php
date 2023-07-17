<?php

namespace App\Controller;

use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use App\Entity\Companies;
use App\Repository\CompaniesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Component\Process\Process;
//use Symfony\Component\Cache\Adapter\RedisAdapter;
use Predis\Client;


class SearchController extends AbstractController
{
    private $redis;
    private $companyRepository;


    public function __construct(Client $redis, CompaniesRepository $companyRepository)
    {
        $this->redis = $redis;
        $this->companyRepository = $companyRepository;
    }

    #[Route('/', name: 'search')]
    public function proccessForm (Request $request, KernelInterface $kernel) {

        //$redis = new Client();
        $registrationCodes = $request->get('registrationCode');
        $clientIp = $request->getClientIp();

        $searchResult = [];
        $companyName = [];
        $companyCodes = [];
        $companyVAT = [];
        $companyAddress = [];
        $companyPhone = [];
        $companyTurnover = [];

        $sessionCodes = $this->getFromRedis($clientIp);

        try {
            foreach($sessionCodes as $code) {
                $company = $this->getFromDatabase($code);

                if ($company) {
                    $searchResult[] = $company['searchResult'];
                    $companyName[] = $company['companyName'];
                    $companyCodes[] = $code;
                    $companyVAT[] = $company['companyVAT'];
                    $companyAddress[] = $company['companyAddress'];
                    $companyTurnover[] = $company['companyTurnover'];
                }
                else {
                    $searchResult[] = false;
                    $companyName[] = '';
                    $companyCodes[] = '';
                    $companyVAT[] = '';
                    $companyAddress[] = '';
                    $companyTurnover[] = [];
                }
            }
        }
        catch (\Exception $e) {

        }

        if (!is_null($registrationCodes)) {
            $registrationCodes = explode(',', $registrationCodes);

        foreach($registrationCodes as $code) {
            $code = trim($code);
        }

        foreach($registrationCodes as $registrationCode) {

            if ($sessionCodes) {
                $exists = in_array($registrationCode, $sessionCodes);
            }
            else {
                $exists = false;
            }

            if ($exists) {
                continue;
            }
            else {
                if (!is_null($registrationCode)) {

                    $company = $this->getFromDatabase($registrationCode);
                    if ($company) {
                        
                        $searchResult[] = $company['searchResult'];
                        $companyName[] = $company['companyName'];
                        $companyCodes[] = $registrationCode;
                        $companyVAT[] = $company['companyVAT'];
                        $companyAddress[] = $company['companyAddress'];
                        $companyTurnover[] = $company['companyTurnover'];
                    }
                    else {
                        $this->executeScraperCommand($kernel, $registrationCode, $clientIp);
        
                        $company = $this->getFromDatabase($registrationCode);
        
                        if ($company) {
                            $searchResult[] = $company['searchResult'];
                            $companyName[] = $company['companyName'];
                            $companyCodes[] = $registrationCode;
                            $companyVAT[] = $company['companyVAT'];
                            $companyAddress[] = $company['companyAddress'];
                            $companyTurnover[] = $company['companyTurnover'];
                        }
                    }
                }
        
            }
        }
        }

        $session = false;
        if (count($companyCodes) > 0) {
            $session = true;
        }

        return $this->render('search/index.html.twig', [
            'searchNumber' => count($searchResult),
            'searchResult' => $searchResult,
            'companyName' => $companyName,
            'registrationCode' => $companyCodes,
            'companyVAT' => $companyVAT,
            'companyAddress' => $companyAddress,
            'companyTurnover' => $companyTurnover,
            'session' => $session,
            //'companyPhone' => $companyPhone,
        ]);

    }

    protected function setValue($dataArray, $parameter) {
        try {
            return $dataArray[$parameter];
        }
        catch (\Exception $e) {

        }

        return 'No data';
    }

    protected function getFromRedis ($key) {
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host' => 'redis', // Replace with your Redis container's hostname
            'port' => 6379, // Replace with the appropriate port
        ]);

        $storedSession = $this->redis->get($key);

        $storedSession = json_decode($storedSession, true);

        return $storedSession;
    }

    protected function getFromDatabase ($filter) {
        $company = $this->companyRepository->findOneBy(['registrationCode' => (int)$filter]);

            if ($company) {
                $searchResult = true;
                $companyName = $company->getCompanyName();
                $companyVAT = $company->getCompanyVAT();
                $companyAddress = $company->getCompanyAddress();
                $companyTurnover = $company->getCompanyTurnover();
            

        $company = [
            'searchResult' => $searchResult,
            'companyName' => $companyName,
            'companyVAT' => $companyVAT,
            'companyAddress' => $companyAddress,
            'companyTurnover' => $companyTurnover
        ];
    }
    else {
        $company = null;
    }

        return $company;
    }

    protected function executeScraperCommand(KernelInterface $kernel, $registrationCode, $clientIp) {
        $application = new Application($kernel);
        
                        $command = $application->find('app:scraper');
                
                        $input = new ArrayInput([
                            'registrationCode' => $registrationCode,
                            'clientIp' => $clientIp,
                        ]);
                    
                        $output = new BufferedOutput();
                        $command->run($input, $output);
    }
}
