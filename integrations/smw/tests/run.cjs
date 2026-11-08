const fs = require('node:fs');
const path = require('node:path');
const {lua,lauxlib,lualib,to_luastring,to_jsstring} = require(process.env.SWS_LUA_RUNTIME || 'fengari');
const root = path.resolve(__dirname, '..');
for (const suite of ['regression-datum.lua', 'schemafelder.lua', 'url.lua']) {
    const L = lauxlib.luaL_newstate();
    lualib.luaL_openlibs(L);
    const fixture = JSON.parse(fs.readFileSync(path.join(__dirname, 'Live-Bestand.json'), 'utf8'));
    fixture.sources = {};
    for (const [title,file] of [['Platon','Platon-Quellen.json'],['Johann Wolfgang von Goethe','Goethe-Quellen.json']]) fixture.sources[title] = JSON.parse(fs.readFileSync(path.join(__dirname,file),'utf8'));
    const asLua = v => v === null ? 'nil' : Array.isArray(v) ? '{'+v.map(asLua).join(',')+'}' : typeof v === 'object' ? '{'+Object.entries(v).map(([k,x])=>'['+JSON.stringify(k)+']='+asLua(x)).join(',')+'}' : JSON.stringify(v);
    let source = 'liveFixtures = ' + asLua(fixture) + '\n';
    for (const [name,file] of [['Modul:SWS/API','Modul-SWS-API.lua'], ['Modul:SWS/config','Modul-SWS-config.lua'], ['Modul:SWS','Modul-SWS.lua']]) {
        source += `package.preload[${JSON.stringify(name)}] = function()\n${fs.readFileSync(path.join(__dirname,'legacy',file),'utf8')}\nend\n`;
    }
    source += fs.readFileSync(path.join(__dirname, suite), 'utf8');
    if (lauxlib.luaL_dostring(L,to_luastring(source)) !== lua.LUA_OK) {
        throw new Error(suite + ': ' + to_jsstring(lua.lua_tostring(L,-1)));
    }
}

// Current public modules: read through the PHP adapter, never annotate in Lua.
{
    const L = lauxlib.luaL_newstate();
    lualib.luaL_openlibs(L);
    let source = '';
    for (const [name, file] of [['Modul:WikiSchema','Modul-WikiSchema.lua']]) {
        source += `package.preload[${JSON.stringify(name)}] = function()\n${fs.readFileSync(path.join(root,'Wikiseiten',file),'utf8')}\nend\n`;
    }
    source += fs.readFileSync(path.join(__dirname,'reader.lua'),'utf8');
    if (lauxlib.luaL_dostring(L,to_luastring(source)) !== lua.LUA_OK) {
        throw new Error('reader.lua: ' + to_jsstring(lua.lua_tostring(L,-1)));
    }
}
