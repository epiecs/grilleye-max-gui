var livechart;
var sessionchart;
var liveTemperatures = [];

window.addEventListener('load', function () {

    // Enable the screen lock checkbox/toggle so that our screen no longer goes to sleep
    // only works in supported browsers

    var dontsleepToggle = document.querySelector('#keepscreenon');

    if(!("wakeLock" in navigator)) 
    {
        dontsleepToggle.disabled = true
    }
    else
    {
        // The toggle button
        dontsleepToggle.addEventListener("click", async function(){

            if(dontsleepToggle.checked)
            {
                try {
                    screenlock = await navigator.wakeLock.request('screen');
                } catch(err) {
                    console.log(err.name, err.message);
                }
                return screenlock;
            }
            else
            {
                if(typeof screenlock !== "undefined" && screenlock != null) 
                {
                    screenlock.release().then(() => {
                        screenlock = null;
                    });
                }
            }
        })
        
        // When we minimize the window, change tab etc... the lock is released. If this happens
        // we need to uncheck the radio button. This is also triggered by devices with a low battery!
        document.addEventListener('visibilitychange', async () => {
            dontsleepToggle.checked = false;
            if(typeof screenlock !== "undefined" && screenlock != null) 
            {
                screenlock.release().then(() => {
                    screenlock = null;
                });
            }
        });
    }

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

            // refresh every second
            await new Promise(resolve => setTimeout(resolve, 1000));
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
        else
        {
            // IF we get a 404 it means that we have no running session
            // To lighten the load on the hyperion api we only check every minute
            await new Promise(resolve => setTimeout(resolve, 60000));
            await getSessionSeries();
        }
    };

    // Used if we need to wait for the grilleye to connect in order to not overload the api 
    var waitSecondsRetry = 60;

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
            grillRefresh.style.display = 'none';

            if(grillData.grill.connected){
                grillConnected.textContent = 'connected';
                grillConnected.classList.add("bg-success");
            }
            else
            {
                grillConnected.textContent = 'disconnected';
                grillConnected.classList.add("bg-danger");

                grillRefresh.style.display = 'inline-block';

                // If the grill is disconnected we do not need to check every second
                // To lighten the load on the hyperion api we only check every minute

                while (waitSecondsRetry > 0) 
                {
                    grillRefreshCountdown.textContent = waitSecondsRetry;
                    await new Promise(resolve => setTimeout(resolve, 1000));
                    waitSecondsRetry--;
                }
                
                waitSecondsRetry = 60
                await getGrillSeries();
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
    var grillRefresh = document.querySelector('#grill-refresh');
    var grillRefreshCountdown = document.querySelector('#grill-refresh-countdown');

    grillRefresh.addEventListener("click", async function(){
        grillRefresh.style.display = 'none';
        waitSecondsRetry = 0;
        // getGrillSeries();

    });

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