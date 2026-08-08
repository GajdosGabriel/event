function t(r,e="EUR"){return new Intl.NumberFormat("sk-SK",{style:"currency",currency:e??"EUR"}).format(r/100)}function a(r,e="EUR"){return r?t(r,e):"Zdarma"}export{a,t as f};
