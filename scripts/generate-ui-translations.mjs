import fs from 'node:fs/promises';
import path from 'node:path';
import ts from 'typescript';

const sourceRoot = path.resolve('resources/js');
const outputFile = path.join(sourceRoot, 'lib', 'englishTranslations.ts');
const arabic = /[\u0600-\u06ff]/;

async function filesIn(directory, extensionPattern = /\.(ts|tsx)$/) {
    const entries = await fs.readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries) {
        const target = path.join(directory, entry.name);
        if (entry.isDirectory()) files.push(...await filesIn(target, extensionPattern));
        if (entry.isFile() && extensionPattern.test(entry.name) && target !== outputFile) files.push(target);
    }

    return files;
}

const phrases = new Set();

for (const file of await filesIn(sourceRoot)) {
    const source = await fs.readFile(file, 'utf8');
    const ast = ts.createSourceFile(file, source, ts.ScriptTarget.Latest, true, file.endsWith('x') ? ts.ScriptKind.TSX : ts.ScriptKind.TS);

    function visit(node) {
        let value = null;

        if (
            ts.isStringLiteral(node)
            || ts.isNoSubstitutionTemplateLiteral(node)
            || ts.isTemplateHead(node)
            || ts.isTemplateMiddle(node)
            || ts.isTemplateTail(node)
            || ts.isJsxText(node)
        ) {
            value = node.text;
        }

        if (value && arabic.test(value)) {
            const normalized = value.replace(/\s+/g, ' ').trim();
            if (normalized && normalized.length <= 500) phrases.add(normalized);
        }

        ts.forEachChild(node, visit);
    }

    visit(ast);
}

for (const phpRoot of ['app', 'routes']) {
    for (const file of await filesIn(path.resolve(phpRoot), /\.php$/)) {
        const source = await fs.readFile(file, 'utf8');

        for (const match of source.matchAll(/(['"])(.*?[\u0600-\u06ff].*?)\1/gu)) {
            const normalized = match[2].replace(/\\(['"])/g, '$1').replace(/\s+/g, ' ').trim();
            if (normalized && normalized.length <= 500) phrases.add(normalized);
        }
    }
}

const sourcePhrases = [...phrases].sort((a, b) => a.localeCompare(b, 'ar'));
const translated = {};
let cursor = 0;

async function worker() {
    while (cursor < sourcePhrases.length) {
        const phrase = sourcePhrases[cursor++];
        const url = new URL('https://translate.googleapis.com/translate_a/single');
        url.searchParams.set('client', 'gtx');
        url.searchParams.set('sl', 'ar');
        url.searchParams.set('tl', 'en');
        url.searchParams.set('dt', 't');
        url.searchParams.set('q', phrase);

        for (let attempt = 0; attempt < 3; attempt++) {
            try {
                const response = await fetch(url);
                if (!response.ok) throw new Error(`HTTP ${response.status}`);
                const data = await response.json();
                translated[phrase] = data[0].map((part) => part[0]).join('');
                break;
            } catch (error) {
                if (attempt === 2) throw error;
                await new Promise((resolve) => setTimeout(resolve, 500 * (attempt + 1)));
            }
        }
    }
}

await Promise.all(Array.from({ length: 8 }, worker));

const ordered = Object.fromEntries(sourcePhrases.map((phrase) => [phrase, translated[phrase]]));
const contents = `// Generated from the Arabic UI source strings. Keep keys unchanged.\nexport const englishTranslations: Record<string, string> = ${JSON.stringify(ordered, null, 4)};\n`;
await fs.writeFile(outputFile, contents, 'utf8');
console.log(`Generated ${sourcePhrases.length} English UI translations.`);
