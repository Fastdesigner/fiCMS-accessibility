import fs from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const source=fs.readFileSync('assets/js/services/accessibility_audit.js','utf8');
for(const test of [{webdriver:true,allowed:1,expected:0},{webdriver:false,allowed:0,expected:0},{webdriver:false,allowed:1,expected:1}]){
 let imports=0,runs=0,posts=0;
 const context=vm.createContext({document:{currentScript:{src:'https://example.test/plugin/services/accessibility_audit.js'}},window:{},navigator:{webdriver:test.webdriver},fiCMS:{vars:{global:{refresh_allowed:test.allowed}}},FormData,URL,fiCMS__refresh:async()=>posts++});
 const module=new vm.SyntheticModule(['AccessibilityAudit'],function(){this.setExport('AccessibilityAudit',{run:async()=>{runs++;return {};}});},{context});
 await module.link(()=>{});await module.evaluate();
 const script=new vm.Script(source,{importModuleDynamically:async()=>{imports++;return module;}});
 script.runInContext(context);await new Promise(resolve=>setTimeout(resolve,10));
 assert.equal(imports,test.expected);assert.equal(runs,test.expected);assert.equal(posts,test.expected);assert.equal(!!context.window.fiCMSAccessibilityRunning,!!test.expected);
}
console.log('PASS native bootstrap: automated browser and server bot flag avoid import/audit/POST; genuine browser completes');
