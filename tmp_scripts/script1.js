
            (function(){
              var radios = document.querySelectorAll('input[type="radio"][data-mc-single]');
              var field = document.getElementById('mcField');
              // Mark initial state
              radios.forEach(function(r){ r.dataset.selected = r.checked ? '1' : '0'; });
              // Toggle-on-click behavior: clicking the currently selected radio unchecks it and clears the field
              radios.forEach(function(r){
                r.addEventListener('click', function(ev){
                  if (r.dataset.selected === '1') {
                    // Deselect
                    r.checked = false;
                    r.dataset.selected = '0';
                    if (field) { field.value = ''; }
                    // Prevent the default selection re-apply
                    ev.preventDefault();
                    return false;
                  }
                  // Select this and unselect others
                  radios.forEach(function(o){ o.dataset.selected = '0'; });
                  r.dataset.selected = '1';
                  if (field) { field.value = (r.getAttribute('data-station') || r.value); }
                });
                // Also update field on change (keyboard navigation)
                r.addEventListener('change', function(){ if (r.checked && field) { field.value = (r.getAttribute('data-station') || r.value); } });
              });
            })();
          