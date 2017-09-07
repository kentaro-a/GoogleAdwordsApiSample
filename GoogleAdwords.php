<?php

require __DIR__ . '/./googleads-php-lib/vendor/autoload.php';
use Google\AdsApi\AdWords\AdWordsServices;
use Google\AdsApi\AdWords\AdWordsSession;
use Google\AdsApi\AdWords\AdWordsSessionBuilder;
use Google\AdsApi\AdWords\v201708\cm\Keyword;
use Google\AdsApi\AdWords\v201708\cm\Language;
use Google\AdsApi\AdWords\v201708\cm\NetworkSetting;
use Google\AdsApi\AdWords\v201708\cm\Paging;
use Google\AdsApi\AdWords\v201708\cm\ApiException;
use Google\AdsApi\AdWords\v201708\cm\RateExceededError;
use Google\AdsApi\AdWords\v201708\o\AttributeType;
use Google\AdsApi\AdWords\v201708\o\IdeaType;
use Google\AdsApi\AdWords\v201708\o\LanguageSearchParameter;
use Google\AdsApi\AdWords\v201708\o\NetworkSearchParameter;
use Google\AdsApi\AdWords\v201708\o\RelatedToQuerySearchParameter;
use Google\AdsApi\AdWords\v201708\o\RequestType;
use Google\AdsApi\AdWords\v201708\o\TargetingIdeaSelector;
use Google\AdsApi\AdWords\v201708\o\TargetingIdeaService;
use Google\AdsApi\Common\OAuth2TokenBuilder;
use Google\AdsApi\Common\Util\MapEntries;

class GoogleAdwords {

    const INI_FILE = "path/to/adsapi_php.ini";

    public static function main($keyword) {
        $oAuth2Credential = (new OAuth2TokenBuilder())
            ->fromFile(self::INI_FILE)
            ->build();

        $session = (new AdWordsSessionBuilder())
            ->fromFile(self::INI_FILE)
            ->withOAuth2Credential($oAuth2Credential)
            ->build();

        return self::getVolume(new AdWordsServices(), $session, $keyword);
    }

    public static function get(AdWordsServices $adWordsServices, AdWordsSession $session, $keyword) {
        $ret = [];
        $ret["original_keyword"] = $keyword;
        $ret["keyword"] = "";
        $ret["volume"] = 0;
        $ret["competition"] = 0;

        try {
            $targetingIdeaService = $adWordsServices->get($session, TargetingIdeaService::class);
            $selector = new TargetingIdeaSelector();

            // Set "STATS" to Request type.
            $selector->setRequestType(RequestType::STATS);
            $selector->setIdeaType(IdeaType::KEYWORD);
            // Set Attributes you want to get.
            $selector->setRequestedAttributeTypes([
                AttributeType::KEYWORD_TEXT,
                AttributeType::COMPETITION,
                AttributeType::TARGETED_MONTHLY_SEARCHES,
            ]);
            $searchParameters = [];
            $relatedToQuerySearchParameter = new RelatedToQuerySearchParameter();
            $relatedToQuerySearchParameter->setQueries([$keyword]);
            $searchParameters[] = $relatedToQuerySearchParameter;
            $languageParameter = new LanguageSearchParameter();
            // Japanese
            $jp = new Language();
            $jp->setId(1005);
            $languageParameter->setLanguages([$jp]);
            $searchParameters[] = $languageParameter;
            $networkSetting = new NetworkSetting();
            $networkSetting->setTargetGoogleSearch(true);
            $networkSetting->setTargetSearchNetwork(false);
            $networkSetting->setTargetContentNetwork(false);
            $networkSetting->setTargetPartnerSearchNetwork(false);
            $networkSearchParameter = new NetworkSearchParameter();
            $networkSearchParameter->setNetworkSetting($networkSetting);
            $searchParameters[] = $networkSearchParameter;
            $selector->setSearchParameters($searchParameters);
            $selector->setPaging(new Paging(0, 1));

            $page = $targetingIdeaService->get($selector);
            if ($page->getEntries() !== null && $page->getTotalNumEntries() > 0) {
                foreach ($page->getEntries() as $targetingIdea) {
                    $data = MapEntries::toAssociativeArray($targetingIdea->getData());
                    $ret["keyword"] = $data[AttributeType::KEYWORD_TEXT]->getValue();
                    $ret["volume"] =
                        ($data[AttributeType::TARGETED_MONTHLY_SEARCHES]->getValue() !== null) ? $data[AttributeType::TARGETED_MONTHLY_SEARCHES]->getValue()[0]->getCount() : 0;
                    $ret["competition"] = ($data[AttributeType::COMPETITION]->getValue() !== null) ? $data[AttributeType::COMPETITION]->getValue() : 0;
                    $ret["competition"] = round($ret["competition"],4);
                }
            }

        } catch (ApiException $ae) {
            foreach ($ae->getErrors() as $error) {
                if ($error instanceof RateExceededError) {
                    // You had better handle exeptions with based on this.
                    // $ret["retry_interval"] = $error->getRetryAfterSeconds() + 1;
                }
            }

        } catch (\Exception $e) {
            // Do something...

        } finally {
            return $ret;
        }
    }
}
