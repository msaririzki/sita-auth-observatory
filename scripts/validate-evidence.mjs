import { readFile, readdir } from 'node:fs/promises';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import Ajv2020 from 'ajv/dist/2020.js';
import addFormats from 'ajv-formats';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const schemaPath = path.join(root, 'schemas/v1/trial-evidence.schema.json');
const fixturesPath = path.join(root, 'fixtures/v1');
const schema = JSON.parse(await readFile(schemaPath, 'utf8'));
const ajv = new Ajv2020({ allErrors: true, strict: true });

addFormats(ajv);

const validate = ajv.compile(schema);
const requestedPaths = process.argv.slice(2);
const files =
    requestedPaths.length > 0
        ? requestedPaths.map((value) => path.resolve(value))
        : (await readdir(fixturesPath))
              .filter((name) => name.endsWith('.json'))
              .sort()
              .map((name) => path.join(fixturesPath, name));

let invalid = 0;

for (const file of files) {
    const evidence = JSON.parse(await readFile(file, 'utf8'));
    const name = path.relative(root, file);

    if (validate(evidence)) {
        console.log(`PASS ${name}`);
        continue;
    }

    invalid += 1;
    console.error(`FAIL ${name}`);
    console.error(ajv.errorsText(validate.errors, { separator: '\n  ' }));
}

if (files.length === 0 || invalid > 0) {
    process.exitCode = 1;
}
