/**
 * Static Nepali label maps for enum fields shown in list/card views
 * that don't receive surveyOpts as a prop.
 * Keep in sync with survey_options table defaults.
 */

const MAPS = {
  inst_ownership_type: {
    govt:        'सरकारी',
    private:     'निजी',
    community:   'सामुदायिक',
    ngo:         'गैसस/एनजीओ',
    cooperative: 'सहकारी',
    joint:       'संयुक्त',
    other:       'अन्य',
  },
  business_type: {
    Single:        'एकल स्वामित्व',
    Partnership:   'साझेदारी',
    PublicCompany: 'सार्वजनिक कम्पनी',
  },
  business_field: {
    AbroadjobsSecurity:        'वैदेशिक रोजगार तथा सुरक्षा',
    AgricultureAnimalHusbandary: 'कृषि तथा पशुपालन',
    ConsultancyBusiness:       'परामर्श सेवा',
    EducationalInstutions:     'शैक्षिक संस्था',
    FancyBusiness:             'फ्यान्सी व्यवसाय',
    FinancialInstutions:       'वित्तीय संस्था',
    HospitalandClinc:          'अस्पताल र क्लिनिक',
    HotelAndRestaurant:        'होटल तथा रेस्टुरेन्ट',
    InformationAndCommunication: 'सूचना र संचार',
    MechinaryMaintenance:      'यन्त्र उपकरण मर्मत',
    OtherShopBusiness:         'अन्य पसल व्यवसाय',
    ProductionIndustry:        'उत्पादन उद्योग',
    Suppliers:                 'सप्लायर्स',
    VehicleAndTrasnportation:  'सवारी साधन तथा यातायात',
  },
}

/**
 * Returns the Nepali label for a given category+value pair.
 * Falls back to the raw value if no match found.
 */
export function fieldLabel(category, value) {
  if (!value) return '—'
  return MAPS[category]?.[value] ?? value
}
