<?php
namespace App\Command;

use Symfony\Component\Console\Command\Command;
use App\Entity\Companies;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpFoundation\Response;
use Predis\Client;

class ScraperCommand extends Command
{
    private $redis;
    private $entityManager;

    public function __construct(Client $redis, EntityManagerInterface $entityManager)
    {
        $this->redis = $redis;
        $this->entityManager = $entityManager;

        parent::__construct();
    }

    protected function configure()
    {
        $this->setName("app:scraper")
            ->setDescription("Scrapes data from the website")
            ->addArgument("registrationCode", InputArgument::REQUIRED, "The registration code for scraping")
            ->addArgument("clientIp", InputArgument::REQUIRED, "IP of the client");
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
        {
            //Get arguments
            $registrationCode = $input->getArgument("registrationCode");
            $clientIp = $input->getArgument("clientIp");

            $client = HttpClient::create();
            //Go to the page where results are shown and send POST request
            $response = $client->request("POST", "https://rekvizitai.vz.lt/en/company-search/1/", ["body" => ["name" => "", "word" => "", "code" => $registrationCode, "codepvm" => "", "city" => "", "search_terms" => "", "street" => "", "employeesMin" => "", "employeesMax" => "", "salaryMin" => "", "salaryMax" => "", "debtMin" => "", "debtMax" => "", "transportMax" => "", "salesRevenueMin" => "", "salesRevenueMax" => "", "netProfitMin" => "", "netProfitMax" => "", "registeredFrom" => "", "registeredTo" => "", "order" => 1, "resetFilter" => 0, ], ]);

            $statusCode = $response->getStatusCode();
            $responseContent = $response->getContent();

            $crawler = new Crawler($responseContent);
            //Process response
            $scrapedData = $this->processSearchResponse($crawler, $statusCode);
            //Save or update data if not null to database and add to Redis
            if ($scrapedData !== null)
            {

                $companyRepository = $this
                    ->entityManager
                    ->getRepository(Companies::class);
                $company = $companyRepository->findOneBy(['registrationCode' => $scrapedData['registrationCode']]);

                if ($company)
                {
                    $company->setCompanyName($scrapedData['companyName']);
                    $company->setCompanyVAT($scrapedData['companyVAT']);
                    $company->setCompanyAddress($scrapedData['companyAddress']);
                    $company->setCompanyTurnover($scrapedData['companyTurnover']);
                }
                else
                {
                    $company = new Companies();

                    $company->setCompanyName($scrapedData['companyName']);
                    $company->setRegistrationCode((int)$scrapedData['registrationCode']);
                    $company->setCompanyVAT($scrapedData['companyVAT']);
                    $company->setCompanyAddress($scrapedData['companyAddress']);
                    $company->setCompanyTurnover($scrapedData['companyTurnover']);
                }

                $this
                    ->entityManager
                    ->persist($company);
                $this
                    ->entityManager
                    ->flush();

                $this->redis = new Client(['scheme' => 'tcp', 'host' => 'redis', 'port' => 6379, ]);

                $storedSession = $this
                    ->redis
                    ->get($clientIp);

                $storedSession = json_decode($storedSession, true);

                if ($storedSession)
                {
                    $storedSession[] = $registrationCode;

                    $storedSession = json_encode($storedSession);

                    $this
                        ->redis
                        ->set($clientIp, $storedSession, 'ex', 3600);
                }
                else
                {
                    $storedSession = [$registrationCode];

                    $storedSession = json_encode($storedSession);

                    $this
                        ->redis
                        ->set($clientIp, $storedSession, 'ex', 3600);
                }

                return Command::SUCCESS;
            }
            else {
                return Command::FAILURE;
            }
        }

        private function processSearchResponse(Crawler $crawler, int $statusCode)
        {
            $client = HttpClient::create();

            if ($statusCode === Response::HTTP_OK)
            {
                //Crawl url to the company page
                $resultUrl = $this->crawlerFilterAttr($crawler, ".company-title", "href");

                if (!is_null($resultUrl))
                {

                    $response = $client->request("GET", $resultUrl);

                    $responseStatusCode = $response->getStatusCode();

                    if ($responseStatusCode === Response::HTTP_OK)
                    {

                        $responseContent = $response->getContent();
                        $crawler = new Crawler($responseContent);
                        //Crawll all the necessary components
                        //Custom functions are realize to catch exception, because wned nothing is find (node list is empty) exception is thrown
                        $companyName = $this->crawlerFilterText($crawler, ".title");

                        $companyNumber = $this->crawlerFilterNextText($crawler, 'td:contains("Registration code")');

                        $companyVAT = $this->crawlerFilterNextText($crawler, 'td:contains("VAT")');

                        $companyAddress = $this->crawlerFilterNextText($crawler, 'td:contains("Address")');

                        //Realisation of the storing company`s phone
                        /* $phoneUrl = "https://rekvizitai.vz.lt" . $this->crawlerFilterNextAttr($crawler, 'td:contains("Mobile phone")', "src");
                        
                        
                        if ($phoneUrl || $phoneUrl !== "")
                        {
                            $companyPhone = $client->request("GET", $phoneUrl)->getContent();
                            $phonePath = 'public/images/' . hash("md5", $companyName) . ".gif";
                            file_put_contents($phonePath, $companyPhone);
                        }
                        else
                        {
                            $phonePath = "";
                        } */

                        $turnover = [];

                        $companyTurnoverUrl = $this->crawlerFilterAttr($crawler, 'a:contains("Historical turnover")', "href");

                        if ($companyTurnoverUrl)
                        {
                            //If turnover exists, then it will be saved in 2D array using foreach function
                            $response = $client->request("GET", $companyTurnoverUrl);

                            $responseStatusCode = $response->getStatusCode();

                            if ($responseStatusCode === Response::HTTP_OK)
                            {

                                $responseContent = $response->getContent();

                                $crawler = new Crawler($responseContent);

                                $table = $this->crawlerFilter($crawler, ".postCodes.table.currency-table.finances-table");

                                $rows = $this->crawlerFilter($table, "tr");

                                $rows->each(function (Crawler $row, $rowIndex) use (&$turnover)
                                {
                                    $columns = $this->crawlerFilter($row, "td, th");

                                    $rowData = [];

                                    $columns->each(function (Crawler $column, $columnIndex) use (&$rowData)
                                    {
                                        $rowData[] = $this->crawlerText($column);
                                    });

                                    $turnover[] = $rowData;
                                });
                            }
                        }

                        $companyData = ["companyName" => $companyName, "registrationCode" => $companyNumber, "companyVAT" => $companyVAT, "companyAddress" => $companyAddress,
                        //"companyPhone" => $phonePath,
                        "companyTurnover" => $turnover, ];
                        
                        return $companyData;
                    }
                }
                else
                {
                    return null;
                }
            }
            else
            {
                return null;
            }
        }
        private function crawlerFilter(Crawler $crawler, string $filter)
        {
            try
            {
                return $crawler->filter($filter);
            }
            catch(\Exception $e)
            {
                return null;
            }
        }

        private function crawlerText(Crawler $crawler)
        {
            try
            {
                return $crawler->text();
            }
            catch(\Exception $e)
            {
                return null;
            }
        }

        private function crawlerFilterAttr(Crawler $crawler, string $filter, string $attr)
        {
            try
            {
                return $crawler->filter($filter)->attr($attr);
            }
            catch(\Exception $e)
            {
                return null;
            }
        }

        private function crawlerFilterText(Crawler $crawler, string $filter)
        {
            try
            {
                return $crawler->filter($filter)->text();
            }
            catch(\Exception $e)
            {
                return null;
            }
        }

        private function crawlerFilterNextText(Crawler $crawler, string $filter)
        {
            try
            {
                return $crawler->filter($filter)->nextAll()
                    ->first()
                    ->text();
            }
            catch(\Exception $e)
            {
                return null;
            }
        }

        private function crawlerFilterNextAttr(Crawler $crawler, string $filter, string $attr)
        {
            try
            {
                return $crawler->filter($filter)->nextAll()
                    ->first()
                    ->children()
                    ->attr($attr);
            }
            catch(\Exception $e)
            {
                return null;
            }
        }
    }