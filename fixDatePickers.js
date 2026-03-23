const fs = require('fs');
const path = require('path');

function walkDir(dir, callback) {
  fs.readdirSync(dir).forEach(f => {
    let dirPath = path.join(dir, f);
    let isDirectory = fs.statSync(dirPath).isDirectory();
    isDirectory ? walkDir(dirPath, callback) : callback(path.join(dir, f));
  });
}

walkDir('resources/js', (file) => {
    if (!file.endsWith('.vue')) return;
    
    let content = fs.readFileSync(file, 'utf8');
    let modified = false;
    
    content = content.replace(/<VueDatePicker[\s\S]*?>/g, (match) => {
        if (match.includes('format="') || match.includes(':format="')) return match;
        
        modified = true;
        if (match.includes('enable-time-picker="false"') || match.includes(':enable-time-picker="false"')) {
            return match.replace('<VueDatePicker', '<VueDatePicker format="yyyy/MM/dd"');
        } else {
            return match.replace('<VueDatePicker', '<VueDatePicker format="yyyy/MM/dd HH:mm"');
        }
    });
    
    if (modified) {
        fs.writeFileSync(file, content, 'utf8');
        console.log(`Updated ${file}`);
    }
});
