/**
 * 録画を再生位置を変えられる普通の MP4 にする。
 * MediaRecorder の mp4 は断片化 MP4 で、シーク用の索引が無く再生位置を変えられないプレイヤーが多い。
 * そこで WebCodecs で canvas の映像とタブの音声を直接エンコードし、mp4-muxer で索引付きの MP4 に組み立てる。
 */
var CompareMp4 = (function () {
    var FPS = 30;
    var KEYFRAME_EVERY = FPS * 2;
    var VIDEO_BITRATE = 8000000;
    var AUDIO_BITRATE = 256000;
    /** エンコードが追いつかない時は、メモリを食いつぶさないようにこのフレーム数を超えた分を捨てる */
    var MAX_QUEUE = 10;
    /** H.264 の高いレベルから順に試す (大きい画面ほど高いレベルが要る) */
    var VIDEO_CODECS = ['avc1.640033', 'avc1.640028', 'avc1.4d0033', 'avc1.42003e'];

    function supported() {
        return !!(window.VideoEncoder && window.AudioEncoder && window.MediaStreamTrackProcessor && window.Mp4Muxer);
    }

    function firstSupported(configs, isSupported) {
        return configs.reduce(function (found, config) {
            return found.then(function (hit) {
                if (hit) return hit;
                return isSupported(config).then(function (res) { return res.supported ? config : null; });
            });
        }, Promise.resolve(null));
    }

    function pickVideoConfig(width, height) {
        var configs = VIDEO_CODECS.map(function (codec) {
            return { codec: codec, width: width, height: height, bitrate: VIDEO_BITRATE, framerate: FPS, avc: { format: 'avc' } };
        });
        return firstSupported(configs, VideoEncoder.isConfigSupported.bind(VideoEncoder));
    }

    /** AAC を優先する。環境によって AAC でエンコードできないので、その時は Opus にする */
    function pickAudioConfig(sampleRate, channels) {
        var configs = ['mp4a.40.2', 'opus'].map(function (codec) {
            return { codec: codec, sampleRate: sampleRate, numberOfChannels: channels, bitrate: AUDIO_BITRATE };
        });
        return firstSupported(configs, AudioEncoder.isConfigSupported.bind(AudioEncoder));
    }

    /**
     * 音声の形式 (サンプルレート・チャンネル数) は実際に届いた最初のデータを見ないと分からない
     * @returns {Promise<{reader: ReadableStreamDefaultReader, first: AudioData}|null>}
     */
    function openAudio(track) {
        if (!track) return Promise.resolve(null);
        var reader = new MediaStreamTrackProcessor({ track: track }).readable.getReader();
        return reader.read().then(function (result) {
            return result.done ? null : { reader: reader, first: result.value };
        });
    }

    function createMuxer(video, audio) {
        return new Mp4Muxer.Muxer({
            target: new Mp4Muxer.ArrayBufferTarget(),
            video: { codec: 'avc', width: video.width, height: video.height, frameRate: FPS },
            audio: audio ? { codec: audio.codec === 'opus' ? 'opus' : 'aac', numberOfChannels: audio.numberOfChannels, sampleRate: audio.sampleRate } : undefined,
            fastStart: 'in-memory',
            // 映像と音声で時刻の起点が違うので、それぞれ最初のデータを 0 に揃える
            firstTimestampBehavior: 'offset',
        });
    }

    /** 音声を読み続けてエンコーダに渡す。止める時は reader.cancel() で抜ける */
    function pumpAudio(reader, encoder, first) {
        encoder.encode(first);
        first.close();
        (function next() {
            reader.read().then(function (result) {
                if (result.done) return;
                if (encoder.state === 'configured') encoder.encode(result.value);
                result.value.close();
                next();
            });
        })();
    }

    /**
     * @param {HTMLCanvasElement} canvas 録る映像
     * @param {MediaStreamTrack|undefined} audioTrack タブの音声 (共有されなかったら無し)
     * @returns {Promise<{addFrame: Function, stop: Function}>} 対応していない時は reject
     */
    function create(canvas, audioTrack) {
        var audio = null;
        return openAudio(audioTrack).then(function (opened) {
            audio = opened;
            return Promise.all([
                pickVideoConfig(canvas.width, canvas.height),
                audio ? pickAudioConfig(audio.first.sampleRate, audio.first.numberOfChannels) : null,
            ]);
        }).then(function (configs) {
            if (!configs[0] || (audio && !configs[1])) throw new Error('codec not supported');
            return start(canvas, configs[0], configs[1], audio);
        });
    }

    function start(canvas, videoConfig, audioConfig, audio) {
        var muxer = createMuxer(videoConfig, audioConfig);
        var fail = function (e) { console.error(e); };
        var videoEncoder = new VideoEncoder({ output: function (chunk, meta) { muxer.addVideoChunk(chunk, meta); }, error: fail });
        videoEncoder.configure(videoConfig);

        var audioEncoder = null;
        if (audio) {
            audioEncoder = new AudioEncoder({ output: function (chunk, meta) { muxer.addAudioChunk(chunk, meta); }, error: fail });
            audioEncoder.configure(audioConfig);
            pumpAudio(audio.reader, audioEncoder, audio.first);
        }

        var frameCount = 0;
        var lastFrameAt = 0;
        return {
            /** 描画のたびに呼ばれる。FPS を超える分と、エンコードが詰まっている時は捨てる */
            addFrame: function () {
                var now = performance.now();
                if (now - lastFrameAt < 1000 / FPS - 1 || videoEncoder.encodeQueueSize > MAX_QUEUE) return;
                lastFrameAt = now;
                var frame = new VideoFrame(canvas, { timestamp: Math.round(now * 1000) });
                videoEncoder.encode(frame, { keyFrame: frameCount++ % KEYFRAME_EVERY === 0 });
                frame.close();
            },
            /** @returns {Promise<Blob>} */
            stop: function () {
                if (audio) audio.reader.cancel();
                return Promise.all([videoEncoder.flush(), audioEncoder ? audioEncoder.flush() : null]).then(function () {
                    muxer.finalize();
                    return new Blob([muxer.target.buffer], { type: 'video/mp4' });
                });
            },
        };
    }

    return { supported: supported, create: create };
})();
