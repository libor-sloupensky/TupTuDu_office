/**
 * Doplní do vygenerovaného AndroidManifest.xml intent-filter pro deep link
 * cz.tuptudu.office://auth/done?token=XXX — návrat z Google OAuth Custom Tabu.
 *
 * Složka android/ je v .gitignore a vzniká až příkazem `npx cap add android`,
 * takže manifest se musí patchnout po každém vygenerování projektu.
 * Skript je idempotentní — opakované spuštění nic nerozbije.
 *
 * Spouští se automaticky jako součást `npm run android:sync`.
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';

const MANIFEST = 'android/app/src/main/AndroidManifest.xml';
const ZNACKA = 'android:scheme="cz.tuptudu.office"';

const INTENT_FILTER = `
            <!-- Deep link pro návrat z Google OAuth Custom Tabu -->
            <!-- cz.tuptudu.office://auth/done?token=XXX -->
            <intent-filter android:autoVerify="false">
                <action android:name="android.intent.action.VIEW" />
                <category android:name="android.intent.category.DEFAULT" />
                <category android:name="android.intent.category.BROWSABLE" />
                <data android:scheme="cz.tuptudu.office" android:host="auth" />
            </intent-filter>
`;

if (!existsSync(MANIFEST)) {
    console.error(`Chybí ${MANIFEST} — nejdřív spusť: npx cap add android`);
    process.exit(1);
}

const puvodni = readFileSync(MANIFEST, 'utf8');

if (puvodni.includes(ZNACKA)) {
    console.log('Deep link už v manifestu je — přeskakuji.');
    process.exit(0);
}

// Vložíme za poslední </intent-filter> uvnitř MainActivity (LAUNCHER filtr)
const pozice = puvodni.lastIndexOf('</intent-filter>');
if (pozice === -1) {
    console.error('V manifestu nenalezen žádný <intent-filter> — patch se nepovedl.');
    process.exit(1);
}

const konec = pozice + '</intent-filter>'.length;
const novy = puvodni.slice(0, konec) + '\n' + INTENT_FILTER + puvodni.slice(konec);

writeFileSync(MANIFEST, novy, 'utf8');
console.log('Deep link cz.tuptudu.office://auth přidán do manifestu.');
