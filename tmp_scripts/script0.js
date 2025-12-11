
          (function(){
            const rank = {
              'sleeper': 5,
              '1st_class': 4,
              'seat_reserved': 3,
              'couchette': 3,
              '2nd_class': 2,
              'other': 2,
              'free_seat': 1
            };
            function bindRow(row, idx){
              const selBuy = row.querySelector('select[name="leg_class_purchased['+idx+']"]');
              const selDel = row.querySelector('select[name="leg_class_delivered['+idx+']"]');
              if (!selBuy || !selDel) return;
              // Hidden downgrade flag to submit
              let hid = row.querySelector('input[name="leg_downgraded['+idx+']"]');
              if (!hid) {
                hid = document.createElement('input');
                hid.type = 'hidden';
                hid.name = 'leg_downgraded['+idx+']';
                row.appendChild(hid);
              }
              const auto = () => {
                const rBuy = rank[selBuy.value] || 0;
                const rDel = rank[selDel.value] || 0;
                const downg = rDel > 0 && rBuy > rDel;
                hid.value = downg ? '1' : '';
              };
              selBuy.addEventListener('change', auto);
              selDel.addEventListener('change', auto);
              auto();
            }
            document.querySelectorAll('#perLegDowngrade table tbody tr').forEach((tr,i)=>bindRow(tr,i));
          })();
        