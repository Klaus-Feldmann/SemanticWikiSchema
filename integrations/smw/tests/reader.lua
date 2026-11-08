local last
mw = { text = { trim = function(s) return s:match('^%s*(.-)%s*$') end,
    nowiki = function(s) return s end } }
local adapter = {
    hasAutomaticAnnotations = function() return true end,
    collectFields = function(options)
        last = options
        return { fields = { { name = 'Studium', values = {
            { value = 'Universität Wien' }, { value = 'Universität Wien' },
            { value = 'Leipzig' }
        } } } }
    end
}
package.preload['mw.sws'] = function() return adapter end
local p = require('Modul:WikiSchema')
assert(package.loaded['Modul:SWS/API'] == nil)
assert(p.werte({args = {[1]='Studium'}}) == 'Universität Wien, Leipzig')
assert(last.origin == 'all')
assert(p.wert({args = {[1]='Studium', index='2', herkunft='eigene'}}) == 'Leipzig')
assert(last.origin == 'own')
assert(p.label({args = {[1]='Studium'}}) == 'Studium')
assert(last.origin == 'own')
assert(p.annotieren({args={}}) == '')
adapter.hasAutomaticAnnotations = function() return false end
assert(not pcall(p.annotieren, {args={}}))
adapter.collectFields = nil
assert(not pcall(p.wert, {args={[1]='Studium'}}))
print('Current Lua reader adapter: 10 checks passed')
