var livechart;
var sessionchart;
var liveTemperatures = [];

window.addEventListener('load', function () {

    async function initializeLiveTemperatures() {

        let response = await fetch('/api/probes/dashboard', {
            cache: 'no-cache'
        });

        if(response.ok)
        {
            try {
                liveTemperatures = JSON.parse(await response.text());
            }
            catch (error) {
                //Sometimes the hyperion API times out. This catches the error and waits 2 seconds before trying again
                await new Promise(resolve => setTimeout(resolve, 2000));
                await initializeLiveTemperatures();
            }

            livechart.updateSeries(liveTemperatures);

            //Refresh all data every minute to prevent memory leaks
            await new Promise(resolve => setTimeout(resolve, 60000));
            await initializeLiveTemperatures();
        }
    };
    
    async function getSessionSeries() {

        let response = await fetch('/api/probes/currentsession', {
            cache: 'no-cache'
        });

        if(response.ok)
        {
            try {
                sessionTemperatures = JSON.parse(await response.text());
            }
            catch (error) {
                //Sometimes the hyperion API times out. This catches the error and waits 2 seconds before trying again
                await new Promise(resolve => setTimeout(resolve, 2000));
                await getSessionSeries();
            }

            sessionchart.updateSeries(sessionTemperatures);

            //Refresh all data every minute to prevent memory leaks
            await new Promise(resolve => setTimeout(resolve, 60000));
            await getSessionSeries();
        }
    };

    async function getGrillSeries() {

        let response = await fetch('/api/grill', {
            cache: 'no-cache'
        });

        if(response.ok)
        {
            try {
                var grillData = JSON.parse(await response.text());
            }
            catch (error) {
                //Sometimes the hyperion API times out. This catches the error and waits 2 seconds before trying again
                await new Promise(resolve => setTimeout(resolve, 2000));
                await getGrillSeries();
            }

            //GRILL

            //Name
            grillName.textContent = grillData.grill.name;
            
            //Battery
            grillBattery.textContent = grillData.grill.battery;
            grillBattery.parentElement.classList.remove("bg-success", "bg-warning" ,"bg-danger");

            if(grillData.grill.battery >= 80){
                grillBattery.parentElement.classList.add("bg-success");
            }
            else if(grillData.grill.battery < 80 && grillData.grill.battery >= 40){
                grillBattery.parentElement.classList.add("bg-warning");
            }
            else{
                grillBattery.parentElement.classList.add("bg-danger");
            }

            //Connected
            grillConnected.classList.remove("bg-success", "bg-danger");

            if(grillData.grill.connected){
                grillConnected.textContent = 'connected';
                grillConnected.classList.add("bg-success");
            }
            else
            {
                grillConnected.textContent = 'disconnected';
                grillConnected.classList.add("bg-danger");
            }
            
            //PROBES
            grillData.probes.forEach(probe => {
                probesCards[probe.index].temperature.textContent = probe.temperature;
                probesCards[probe.index].temperaturesetting.textContent = probe.temperaturesetting;
                probesCards[probe.index].preset.textContent = probe.preset;
                probesCards[probe.index].timer.textContent = probe.timer;
                probesCards[probe.index].donetime.textContent = probe.donetime;

                //push data on graph stack
                if (typeof liveTemperatures[probe.index - 1] !== 'undefined' && probe.rawtemperature != null) {
                    liveTemperatures[probe.index - 1].data.push({
                        x: grillData.grill.timestamp,
                        y: probe.rawtemperature
                    });
                }
            });

            livechart.updateSeries(liveTemperatures);
        }

        await new Promise(resolve => setTimeout(resolve, 1000));
        await getGrillSeries();
    };

    //Grill element selectors
    var grillName = document.querySelector('#grill-name');
    var grillBattery = document.querySelector('#grill-battery span');
    var grillConnected = document.querySelector('#grill-connected');

    //Probes element selectors
    var probesCards = {};

    for (let i = 1; i < 9; i++) {
        probesCards[i] = {
            'temperature'       : document.querySelector('#probe-' + i + '-temperature'),
            'temperaturesetting': document.querySelector('#probe-' + i + '-temperaturesetting'),
            'preset'            : document.querySelector('#probe-' + i + '-preset'),
            'timer'             : document.querySelector('#probe-' + i + '-timer'),
            'donetime'          : document.querySelector('#probe-' + i + '-donetime'),
        };
    }

    // Start
    initializeLiveTemperatures();
    getSessionSeries();
    getGrillSeries();
});