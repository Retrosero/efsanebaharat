// Türkçe karakterleri dönüştüren yardımcı fonksiyon
function convertTurkishToBasic(text) {
    const turkishChars = {
        'ı': 'i', 'İ': 'I',
        'ğ': 'g', 'Ğ': 'G',
        'ü': 'u', 'Ü': 'U',
        'ş': 's', 'Ş': 'S',
        'ö': 'o', 'Ö': 'O',
        'ç': 'c', 'Ç': 'C',
        'â': 'a', 'Â': 'A',
        'î': 'i', 'Î': 'I',
        'û': 'u', 'Û': 'U'
    };
    return text.replace(/[ıİğĞüÜşŞöÖçÇâÂîÎûÛ]/g, letter => turkishChars[letter] || letter);
}

// Arama fonksiyonu
function searchProducts(searchText, products) {
    if (!searchText) return products;
    
    searchText = searchText.toLowerCase();
    const basicSearchText = convertTurkishToBasic(searchText);
    
    return products.filter(product => {
        const productName = product.urun_adi.toLowerCase();
        const basicProductName = convertTurkishToBasic(productName);
        
        // Hem orijinal metin hem de dönüştürülmüş metin üzerinde arama yap
        return productName.includes(searchText) || 
               basicProductName.includes(basicSearchText);
    });
}

// Müşteri arama fonksiyonu
function searchCustomers(searchText, customers) {
    if (!searchText) return customers;
    
    searchText = searchText.toLowerCase();
    const basicSearchText = convertTurkishToBasic(searchText);
    
    return customers.filter(customer => {
        const fullName = `${customer.ad} ${customer.soyad}`.toLowerCase();
        const basicFullName = convertTurkishToBasic(fullName);
        
        // Hem orijinal metin hem de dönüştürülmüş metin üzerinde arama yap
        return fullName.includes(searchText) || 
               basicFullName.includes(basicSearchText);
    });
}

// Arama sonuçlarını vurgulama fonksiyonu
function highlightSearchResults(text, searchText) {
    if (!searchText) return text;
    
    const searchTerms = searchText.toLowerCase().split(' ');
    const basicSearchTerms = searchTerms.map(term => convertTurkishToBasic(term.toLowerCase()));
    
    let result = text;
    let basicText = convertTurkishToBasic(text.toLowerCase());
    
    // Her bir arama terimi için vurgulama yap
    searchTerms.forEach((term, index) => {
        const basicTerm = basicSearchTerms[index];
        const regex = new RegExp(`(${term}|${basicTerm})`, 'gi');
        result = result.replace(regex, match => `<mark>${match}</mark>`);
    });
    
    return result;
} 