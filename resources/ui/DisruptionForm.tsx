import * as React from "react";
import {
    DisruptionAnswers,
    emptyDisruptionAnswers,
    evaluateDisruptionFlow,
    fromBackendHooks,
    Q2_SOURCES,
    Q3_OPTIONS,
    toBackendHooks,
    type BackendDisruptionHooks,
} from "./disruption_flow";

type Props = {
    initial?: DisruptionAnswers | BackendDisruptionHooks;
    initialIsBackend?: boolean;
    onChange?: (answers: DisruptionAnswers) => void;
    onReadyChange?: (ready: boolean) => void;
    onBackendChange?: (hooks: BackendDisruptionHooks) => void;
    readOnly?: boolean;
};

export function DisruptionForm({ initial, initialIsBackend, onChange, onReadyChange, onBackendChange, readOnly }: Props) {
    const initialAnswers: DisruptionAnswers = React.useMemo(() => {
        if (!initial) return emptyDisruptionAnswers();
        return initialIsBackend ? fromBackendHooks(initial as BackendDisruptionHooks) : (initial as DisruptionAnswers);
    }, [initial, initialIsBackend]);

    const [answers, setAnswers] = React.useState<DisruptionAnswers>(initialAnswers);
    const evald = evaluateDisruptionFlow(answers);

    React.useEffect(() => {
        onChange?.(answers);
        onReadyChange?.(evald.hooks.ready);
        onBackendChange?.(toBackendHooks(answers));
    }, [answers]);

    return (
        <div className="space-y-4">
            {/* Q1 */}
            <fieldset>
                <legend className="font-medium">1. Var der meddelt afbrydelse/forsinkelse før dit køb?</legend>
                {(["Ja", "Nej"] as const).map(v => (
                    <label key={v} className="mr-4 inline-flex items-center gap-2">
                        <input
                            type="radio"
                            name="preNotified"
                            disabled={readOnly}
                            checked={answers.preNotified === v}
                            onChange={() => setAnswers({ preNotified: v, shownWhere: [], realtimeSeen: undefined })}
                        />
                        {v}
                    </label>
                ))}
            </fieldset>

            {/* Q2 + Q3 vises kun hvis Q1 = Ja */}
            {answers.preNotified === "Ja" && (
                <>
                    <div>
                        <label className="block font-medium mb-1">2. Hvis ja: Hvor blev det vist?</label>
                        <div className="flex flex-wrap gap-2">
                            {Q2_SOURCES.map(opt => (
                                <label key={opt} className="inline-flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        disabled={readOnly}
                                        checked={answers.shownWhere?.includes(opt) ?? false}
                                        onChange={(e) => {
                                            const cur = new Set(answers.shownWhere ?? []);
                                            e.target.checked ? cur.add(opt) : cur.delete(opt);
                                            setAnswers({ ...answers, shownWhere: Array.from(cur) });
                                        }}
                                    />
                                    {opt}
                                </label>
                            ))}
                        </div>
                    </div>

                    <fieldset>
                        <legend className="font-medium">3. Så du realtime-opdateringer under rejsen?</legend>
                        {Q3_OPTIONS.map(opt => (
                            <label key={opt} className="block">
                                <input
                                    type="radio"
                                    name="realtimeSeen"
                                    disabled={readOnly}
                                    checked={answers.realtimeSeen === opt}
                                    onChange={() => setAnswers({ ...answers, realtimeSeen: opt })}
                                />{" "}
                                {opt}
                            </label>
                        ))}
                    </fieldset>
                </>
            )}

            {/* Eval-status (kan fjernes i produktion) */}
            {!evald.hooks.ready && (
                <p className="text-sm text-amber-700">
                    Mangler: {evald.missing.join(", ") || "–"}
                </p>
            )}
        </div>
    );
}
