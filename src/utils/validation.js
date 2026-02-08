// Utility funkce pro validaci emailů a telefonů

/**
 * Pokročilá validace emailové adresy
 * @param {string} email - Email k validaci
 * @returns {Promise<boolean|string>} - true pokud je email validní, jinak chybová zpráva
 */
export const validateEmail = async (email) => {
  if (!email || String(email).trim() === '') return true;

  const trimmedEmail = String(email).trim();

  // Základní regex pro formát emailu (RFC 5322 compliant)
  const emailRegex = /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
  
  if (!emailRegex.test(trimmedEmail)) {
    return 'Neplatný formát e-mailové adresy';
  }

  // Kontrola délky
  if (trimmedEmail.length > 254) {
    return 'E-mailová adresa je příliš dlouhá';
  }

  const parts = trimmedEmail.split('@');
  if (parts.length !== 2) {
    return 'Neplatný formát e-mailové adresy';
  }

  const [localPart, domain] = parts;
  
  // Kontrola lokální části
  if (localPart.length === 0 || localPart.length > 64) {
    return 'Neplatná lokální část e-mailové adresy';
  }

  // Kontrola domény
  if (domain.length === 0 || domain.length > 253) {
    return 'Neplatná doména e-mailové adresy';
  }

  // Kontrola zakázaných znaků v doméně
  if (domain.startsWith('-') || domain.endsWith('-') || domain.includes('..') || domain.startsWith('.') || domain.endsWith('.')) {
    return 'Neplatná doména e-mailové adresy';
  }

  // Kontrola TLD (musí mít alespoň jednu tečku a doménu)
  if (!domain.includes('.') || domain.split('.').length < 2) {
    return 'E-mailová adresa musí obsahovat platnou doménu';
  }

  // Kontrola některých běžných chyb
  const commonDomains = ['gmail.com', 'seznam.cz', 'centrum.cz', 'email.cz', 'outlook.com', 'hotmail.com', 'yahoo.com'];
  const domainLower = domain.toLowerCase();
  
  // Kontrola častých překlepů
  const typos = {
    'gmail.co': 'gmail.com',
    'gmail.cz': 'gmail.com',
    'gmai.com': 'gmail.com',
    'gmial.com': 'gmail.com',
    'seznam.c': 'seznam.cz',
    'seznam.com': 'seznam.cz',
    'centrm.cz': 'centrum.cz',
    'centrum.c': 'centrum.cz'
  };

  if (typos[domainLower]) {
    return `Možná jste mysleli: ${localPart}@${typos[domainLower]}`;
  }

  try {
    // Pokus o ověření existence domény pomocí DNS
    const response = await fetch(`https://dns.google/resolve?name=${domain}&type=MX&cd=false`, {
      method: 'GET',
      headers: {
        'Accept': 'application/json',
      },
      signal: AbortSignal.timeout(5000) // 5 sekund timeout
    });

    if (!response.ok) {
      console.warn('DNS ověření selhalo, pokračuji bez ověření domény');
      return true; // Pokud DNS služba není dostupná, povolíme email
    }

    const data = await response.json();
    
    
  } catch (error) {
    console.warn('Chyba při ověřování e-mailové domény:', error);
    return true; // Pokud ověření selže, povolíme email
  }
};

/**
 * Formátování telefonního čísla
 * @param {string} phone - Telefonní číslo k formátování
 * @returns {string} - Formátované telefonní číslo
 */
export const formatPhoneNumber = (phone) => {
  if (!phone) return '';

  // Konverze na string a odstranění všech ne-číselných znaků kromě +
  let cleaned = String(phone).replace(/[^\d+]/g, '');

  // Pokud číslo nezačína +, přidáme +420
  if (!cleaned.startsWith('+')) {
    // Pokud začíná 00, nahradíme +
    if (cleaned.startsWith('00')) {
      cleaned = '+' + cleaned.substring(2);
    } else {
      cleaned = '+420' + cleaned;
    }
  }

  // Formátování pro české číslo (+420)
  if (cleaned.startsWith('+420')) {
    const number = cleaned.substring(4);
    if (number.length === 9) {
      return `+420 ${number.substring(0, 3)} ${number.substring(3, 6)} ${number.substring(6)}`;
    }
  }

  // Formátování pro slovenské číslo (+421)
  if (cleaned.startsWith('+421')) {
    const number = cleaned.substring(4);
    if (number.length === 9) {
      return `+421 ${number.substring(0, 3)} ${number.substring(3, 6)} ${number.substring(6)}`;
    }
  }

  // Pro ostatní země vrátíme původní formát
  return cleaned;
};

/**
 * Validace telefonního čísla
 * @param {string} phone - Telefonní číslo k validaci
 * @returns {boolean|string} - true pokud je číslo validní, jinak chybová zpráva
 */
export const validatePhoneNumber = (phone) => {
  if (!phone) return true;

  // Konverze na string a odstranění všech ne-číselných znaků kromě +
  let cleaned = String(phone).replace(/[^\d+]/g, '');

  // Pokud číslo nezačína +, přidáme +420
  if (!cleaned.startsWith('+')) {
    if (cleaned.startsWith('00')) {
      cleaned = '+' + cleaned.substring(2);
    } else {
      cleaned = '+420' + cleaned;
    }
  }

  // Validace českého čísla (+420)
  if (cleaned.startsWith('+420')) {
    const number = cleaned.substring(4);
    if (number.length !== 9) {
      return 'České telefonní číslo musí mít 9 číslic po předvolbě +420';
    }
    // České mobilní čísla začínají 6, 7 nebo 9
    const firstDigit = number[0];
    if (!['6', '7', '9'].includes(firstDigit)) {
      return 'Neplatné české telefonní číslo (musí začínat 6, 7 nebo 9)';
    }
    return true;
  }

  // Validace slovenského čísla (+421)
  if (cleaned.startsWith('+421')) {
    const number = cleaned.substring(4);
    if (number.length !== 9) {
      return 'Slovenské telefonní číslo musí mít 9 číslic po předvolbě +421';
    }
    // Slovenské mobilní čísla začínají 9
    const firstDigit = number[0];
    if (firstDigit !== '9') {
      return 'Neplatné slovenské mobilní číslo (musí začínat 9)';
    }
    return true;
  }

  return 'Podporujeme pouze česká (+420) a slovenská (+421) telefonní čísla';
};

/**
 * Hook pro automatické formátování telefonního čísla při psaní
 * @param {Function} setValue - React Hook Form setValue funkce
 * @param {string} fieldName - Název pole ve formuláři
 * @returns {Function} - onChange handler
 */
export const usePhoneFormatter = (setValue, fieldName) => {
  return (e) => {
    const formatted = formatPhoneNumber(String(e.target.value));
    setValue(fieldName, formatted);
  };
};
