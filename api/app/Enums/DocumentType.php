<?php

namespace App\Enums;

/** Identity documents accepted in Nepal, for a person or a business. */
enum DocumentType: string
{
    case Citizenship = 'citizenship';
    case NationalId = 'national_id';
    case Passport = 'passport';
    case DrivingLicence = 'driving_licence';
    case CompanyRegistration = 'company_registration';
    case PanVat = 'pan_vat';

    public function label(): string
    {
        return match ($this) {
            self::Citizenship => 'Citizenship certificate',
            self::NationalId => 'National ID card',
            self::Passport => 'Passport',
            self::DrivingLicence => 'Driving licence',
            self::CompanyRegistration => 'Company registration certificate',
            self::PanVat => 'PAN / VAT certificate',
        };
    }
}
