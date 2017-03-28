<?php

namespace Spatie\Analytics;

use Carbon\Carbon;
use Google_Service_Analytics;
use Illuminate\Support\Collection;
use Illuminate\Support\Traits\Macroable;

class Analytics
{
    use Macroable;

    /** @var \Spatie\Analytics\AnalyticsClient */
    protected $client;

    /** @var string */
    protected $viewId;

    /**
     * @param \Spatie\Analytics\AnalyticsClient $client
     * @param string                            $viewId
     */
    public function __construct(AnalyticsClient $client, string $viewId)
    {
        $this->client = $client;

        $this->viewId = $viewId;
    }

    /**
     * @param string $viewId
     *
     * @return $this
     */
    public function setViewId(string $viewId)
    {
        $this->viewId = $viewId;

        return $this;
    }

    public function fetchVisitorsAndPageViews(Period $period): Collection
    {
        $response = $this->performQuery(
            $period,
            'ga:users,ga:pageviews',
            ['dimensions' => 'ga:date,ga:pageTitle']
        );

        return collect($response['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0]),
                'pageTitle' => $dateRow[1],
                'visitors' => (int) $dateRow[2],
                'pageViews' => (int) $dateRow[3],
            ];
        });
    }

    public function fetchTotalVisitorsAndPageViews(Period $period): Collection
    {
        $response = $this->performQuery(
            $period,
            'ga:users,ga:pageviews',
            ['dimensions' => 'ga:date']
        );

        return collect($response['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'date' => Carbon::createFromFormat('Ymd', $dateRow[0]),
                'visitors' => (int) $dateRow[1],
                'pageViews' => (int) $dateRow[2],
            ];
        });
    }

    public function fetchMostVisitedPages(Period $period, int $maxResults = 20): Collection
    {
        $response = $this->performQuery(
            $period,
            'ga:pageviews',
            [
                'dimensions' => 'ga:pagePath,ga:pageTitle',
                'sort' => '-ga:pageviews',
                'max-results' => $maxResults,
            ]
        );

        return collect($response['rows'] ?? [])
            ->map(function (array $pageRow) {
                return [
                    'url' => $pageRow[0],
                    'pageTitle' => $pageRow[1],
                    'pageViews' => (int) $pageRow[2],
                ];
            });
    }

    public function fetchTopReferrers(Period $period, int $maxResults = 20): Collection
    {
        $response = $this->performQuery($period,
            'ga:pageviews',
            [
                'dimensions' => 'ga:fullReferrer',
                'sort' => '-ga:pageviews',
                'max-results' => $maxResults,
            ]
        );

        return collect($response['rows'] ?? [])->map(function (array $pageRow) {
            return [
                'url' => $pageRow[0],
                'pageViews' => (int) $pageRow[1],
            ];
        });
    }

    public function fetchTopBrowsers(Period $period, int $maxResults = 10): Collection
    {
        $response = $this->performQuery(
            $period,
            'ga:sessions',
            [
                'dimensions' => 'ga:browser',
                'sort' => '-ga:sessions',
            ]
        );

        $topBrowsers = collect($response['rows'] ?? [])->map(function (array $browserRow) {
            return [
                'browser' => $browserRow[0],
                'sessions' => (int) $browserRow[1],
            ];
        });

        if ($topBrowsers->count() <= $maxResults) {
            return $topBrowsers;
        }

        return $this->summarizeTopBrowsers($topBrowsers, $maxResults);
    }

    protected function summarizeTopBrowsers(Collection $topBrowsers, int $maxResults): Collection
    {
        return $topBrowsers
            ->take($maxResults - 1)
            ->push([
                'browser' => 'Others',
                'sessions' => $topBrowsers->splice($maxResults - 1)->sum('sessions'),
            ]);
    }
    
    public function fetchTopOperatingSystems(Period $period) {
        $query = $this->performQuery(
            $period, 
            'ga:sessions',
            [
                'dimensions' => 'ga:operatingSystem',
                'sort' => '-ga:sessions'
            ]
        );
    
        return collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'operatingSystem' => $dateRow[0],
                'sessions' => (int) $dateRow[1]
            ];
        });
    }
    
    public function fetchTopCountries(Period $period, int $maxResults = 10) {
        $query = $this->performQuery(
            $period, 
            'ga:sessions',
            [
                'dimensions' => 'ga:country',
                'sort' => '-ga:sessions'
            ]
        );
    
        $results = collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'country' => $dateRow[0],
                'sessions' => (int) $dateRow[1]
            ];
        });
        
        if ($results->count() > $maxResults) {
            $results = $results
                ->take($maxResults - 1)
                ->push([
                    'country' => 'Others',
                    'sessions' => $results->splice($maxResults - 1)->sum('sessions'),
                ]);
        }
        
        return $results;
    }
    
    public function fetchTopCities(Period $period, int $maxResults = 10) {
        $query = $this->performQuery(
            $period, 
            'ga:sessions',
            [
                'dimensions' => 'ga:city',
                'sort' => '-ga:sessions'
            ]
        );
                
        $results = collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'city' => $dateRow[0],
                'sessions' => (int) $dateRow[1]
            ];
        });
        
        if ($results->count() > $maxResults) {
            $results = $results
                ->take($maxResults - 1)
                ->push([
                    'city' => 'Others',
                    'sessions' => $results->splice($maxResults - 1)->sum('sessions'),
                ]);
        }
        
        return $results;
    }

    public function fetchTopLanguages(Period $period, int $maxResults = 10) {
        $query = $this->performQuery(
            $period,
            'ga:sessions',
            [
                'dimensions' => 'ga:language',
                'sort' => '-ga:sessions'
            ]
            );
    
        $results = collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'language' => $dateRow[0],
                'sessions' => (int) $dateRow[1]
            ];
        });
    
        if ($results->count() > $maxResults) {
            $results = $results
            ->take($maxResults - 1)
            ->push([
                'language' => 'Others',
                'sessions' => $results->splice($maxResults - 1)->sum('sessions'),
            ]);
        }

        return $results;
    }

    public function fetchTopTrafficSources(Period $period) {
        $query = $this->performQuery(
            $period,
            'ga:sessions,ga:pageviews,ga:sessionDuration,ga:exits',
            [
                'dimensions' => 'ga:source,ga:medium',
                'sort' => '-ga:sessions'
            ]
            );
    
        $results = collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'source' => $dateRow[0],
                'sessions' => (int) $dateRow[2]
            ];
        });
    
        return $results;
    }

    public function fetchTopSearchEngines(Period $period) {
        $query = $this->performQuery(
            $period,
            'ga:pageviews,ga:sessionDuration,ga:exits',
            [
                'dimensions' => 'ga:source',
                'filters' => 'ga:medium==cpa,ga:medium==cpc,ga:medium==cpm,ga:medium==cpp,ga:medium==cpv,ga:medium==organic,ga:medium==ppc',
                'sort' => '-ga:pageviews'
            ]
            );
    
        $results = collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'url' => $dateRow[0],
                'sessions' => (int) $dateRow[1]
            ];
        });
    
        return $results;
    }

    public function fetchTopKeywords(Period $period) {
        $query = $this->performQuery(
            $period,
            'ga:sessions',
            [
                'dimensions' => 'ga:keyword',
                'sort' => '-ga:sessions'
            ]
            );
    
        $results = collect($query['rows'] ?? [])->map(function (array $dateRow) {
            return [
                'keyword' => $dateRow[0],
                'sessions' => (int) $dateRow[1]
            ];
        });
    
        return $results;
    }

    public function fetchNumberActiveUsers() {
        $query = $this->performQueryRealTime(
            'rt:activeUsers'
        );
    
        return $query['rows'][0][0];
    }
    
    public function fetchCountriesActiveUsers() {
        $query = $this->performQueryRealTime(
            'rt:activeUsers',
            [
                'dimensions' => 'rt:country'
            ]
        );

        return $query['rows'];
    }
    
    public function fetchCitiesActiveUsers() {
        $query = $this->performQueryRealTime(
            'rt:activeUsers',
            [
                'dimensions' => 'rt:city'
            ]
        );

        return $query['rows'];
    }
    
    public function fetchBrowsersActiveUsers() {
        $query = $this->performQueryRealTime(
            'rt:activeUsers',
            [
                'dimensions' => 'rt:browser'
            ]
        );

        return $query['rows'];
    }
    
    public function fetchOperatingSystemActiveUsers() {
        $query = $this->performQueryRealTime(
            'rt:activeUsers',
            [
                'dimensions' => 'rt:operatingSystem'
            ]
        );

        return $query['rows'];
    }
    
    public function fetchDeviceCategoryActiveUsers() {
        $query = $this->performQueryRealTime(
            'rt:activeUsers',
            [
                'dimensions' => 'rt:deviceCategory'
            ]
        );

        return $query['rows'];
    }
    
    /**
     * Call the query method on the authenticated client.
     *
     * @param Period $period
     * @param string $metrics
     * @param array  $others
     *
     * @return array|null
     */
    public function performQuery(Period $period, string $metrics, array $others = [])
    {
        return $this->client->performQuery(
            $this->viewId,
            $period->startDate,
            $period->endDate,
            $metrics,
            $others
        );
    }

    /**
     * Call the realtime query method on the authenticated client.
     *
     * @param string $metrics
     * @param array  $others
     *
     * @return array|null
     */
    public function performQueryRealTime(string $metrics, array $others = [])
    {
        return $this->client->performQueryRealTime(
            $this->viewId,
            $metrics,
            $others
        );
    }

    /**
     * Get the underlying Google_Service_Analytics object. You can use this
     * to basically call anything on the Google Analytics API.
     *
     * @return \Google_Service_Analytics
     */
    public function getAnalyticsService(): Google_Service_Analytics
    {
        return $this->client->getAnalyticsService();
    }
}
