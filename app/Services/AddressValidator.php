<?php

namespace App\Services;

use App\Models\City;
use App\Models\District;
use Illuminate\Support\Str;

class AddressValidator
{
    /**
     * Türkçe karakterleri normalize eder
     */
    private function normalizeString($str)
    {
        // Önce tüm karakterleri küçük harfe çevir
        $str = mb_strtolower($str, 'UTF-8');
        
        // Türkçe karakterleri değiştir (büyük/küçük harf kombinasyonları)
        $replacements = [
            'İ' => 'i',
            'I' => 'i',
            'Ğ' => 'g',
            'Ü' => 'u',
            'Ş' => 's',
            'Ö' => 'o',
            'Ç' => 'c',
            'ı' => 'i',
            'ğ' => 'g',
            'ü' => 'u',
            'ş' => 's',
            'ö' => 'o',
            'ç' => 'c'
        ];
        
        $str = str_replace(array_keys($replacements), array_values($replacements), $str);

        // Boşlukları temizle
        $str = trim($str);
        
        // Özel karakterleri (nokta gibi) temizle
        $str = preg_replace('/[^a-z0-9]/', '', $str);
        
        return $str;
    }

    /**
     * İki metin arasındaki benzerlik oranını hesaplar
     */
    private function calculateSimilarity($str1, $str2)
    {
        // Her iki metni de normalize et
        $normalized1 = $this->normalizeString($str1);
        $normalized2 = $this->normalizeString($str2);

        // Tam eşleşme kontrolü
        if ($normalized1 === $normalized2) {
            return 100;
        }

        // Metin uzunluklarını kontrol et
        $str1Length = mb_strlen($normalized1, 'UTF-8');
        $str2Length = mb_strlen($normalized2, 'UTF-8');

        // Levenshtein mesafesini hesapla
        $distance = levenshtein($normalized1, $normalized2);
        $maxLength = max($str1Length, $str2Length);

        // Benzerlik oranını hesapla
        return (1 - ($distance / $maxLength)) * 100;
    }

    /**
     * En yüksek benzerlik oranına sahip şehri bulur
     */
    private function findBestMatchingCity($cityName)
    {
        $cities = City::all();
        $bestMatch = null;
        $highestSimilarity = 0;
        $bestMatchName = null;

        foreach ($cities as $city) {
            $similarity = $this->calculateSimilarity($cityName, $city->name);
            
            if ($similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $bestMatch = $city;
                $bestMatchName = $city->name;
            }
        }

        return [
            'city' => $bestMatch,
            'similarity' => $highestSimilarity,
            'matched_name' => $bestMatchName
        ];
    }

    /**
     * En yüksek benzerlik oranına sahip ilçeyi bulur
     */
    private function findBestMatchingDistrict($districtName, $cityId = null)
    {
        if (!$districtName) {
            return [
                'district' => null,
                'similarity' => 0,
                'matched_name' => null
            ];
        }

        $query = District::query();
        if ($cityId) {
            $query->where('city_id', $cityId);
        }
        
        $districts = $query->get();
        $bestMatch = null;
        $highestSimilarity = 0;
        $bestMatchName = null;

        foreach ($districts as $district) {
            $similarity = $this->calculateSimilarity($districtName, $district->name);
            
            if ($similarity > $highestSimilarity) {
                $highestSimilarity = $similarity;
                $bestMatch = $district;
                $bestMatchName = $district->name;
            }
        }

        return [
            'district' => $bestMatch,
            'similarity' => $highestSimilarity,
            'matched_name' => $bestMatchName
        ];
    }

    /**
     * Adres doğrulama işlemini gerçekleştirir
     */
    public function validateAddress($cityName, $districtName = null)
    {
        // 1. İl kontrolü
        $cityMatch = $this->findBestMatchingCity($cityName);
        $city = $cityMatch['city'];
        $citySimilarity = $cityMatch['similarity'];
        $cityMatchedName = $cityMatch['matched_name'];

        // 2. İlçe kontrolü
        $districtMatch = $this->findBestMatchingDistrict($districtName, $city ? $city->id : null);
        $district = $districtMatch['district'];
        $districtSimilarity = $districtMatch['similarity'];
        $districtMatchedName = $districtMatch['matched_name'];

        // Sonuçları hazırla
        $result = [
            'is_valid' => false,
            'city' => null,
            'district' => null,
            'city_similarity' => $citySimilarity,
            'district_similarity' => $districtSimilarity,
            'city_matched_name' => $cityMatchedName,
            'district_matched_name' => $districtMatchedName,
            'needs_review' => false
        ];

        // İl ve ilçe eşleşme durumlarını kontrol et
        if ($citySimilarity >= 75) {
            $result['city'] = $city;
            
            // İlçe kontrolü yapılacaksa
            if ($districtName) {
                if ($districtSimilarity >= 75) {
                    $result['district'] = $district;
                    $result['is_valid'] = true;
                } else {
                    $result['needs_review'] = true;
                }
            } else {
                // İlçe girilmemişse geçerli kabul et
                $result['is_valid'] = true;
            }
        } else {
            $result['needs_review'] = true;
        }

        // İl bulundu ama ilçe bulunamadıysa needs_review true olsun
        if ($result['city'] && !$result['district'] && $districtName) {
            $result['needs_review'] = true;
            $result['is_valid'] = false;
        }

        return $result;
    }
}
