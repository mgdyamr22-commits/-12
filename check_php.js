import * as fs from 'fs';

const content = fs.readFileSync('index.php', 'utf-8');
const lines = content.split('\n');

let openForeachCount = 0;
let endForeachCount = 0;

console.log("Analyzing index.php for foreach/endforeach structures...");

const stack = [];

for (let i = 0; i < lines.length; i++) {
  const line = lines[i];
  const lineNum = i + 1;

  // Check for foreach style: foreach (...) :
  // We match things like foreach(...) : or foreach(...) {
  const foreachMatch = line.match(/foreach\s*\(([^)]+)\)\s*(:|\{)/);
  if (foreachMatch) {
    const type = foreachMatch[2];
    stack.push({ type, lineNum, line: line.trim() });
    if (type === ':') {
      openForeachCount++;
    }
  }

  // Check for endforeach
  if (line.includes('endforeach')) {
    endForeachCount++;
    const last = stack.filter(s => s.type === ':').pop();
    if (last) {
      // remove last colon foreach
      const idx = stack.lastIndexOf(last);
      stack.splice(idx, 1);
    } else {
      console.log(`Error: Found 'endforeach' at line ${lineNum} with no matching 'foreach (...):'`);
    }
  }

  // Check for closing brace
  // Note: this is a simple brace matching, but let's see
}

console.log(`Summary: Found ${openForeachCount} colon-style foreach statements, and ${endForeachCount} endforeach statements.`);
if (stack.filter(s => s.type === ':').length > 0) {
  console.log("Unclosed colon-style foreach statements:");
  stack.filter(s => s.type === ':').forEach(s => {
    console.log(`Line ${s.lineNum}: ${s.line}`);
  });
} else {
  console.log("All colon-style foreach statements are closed successfully.");
}
